<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Genera y guarda los reportes de Operaciones en el disco privado.
 *
 * Cada usuario tiene su carpeta (`operation-reports/{id}`) y sólo descarga lo
 * suyo por una ruta autenticada. Los archivos viven 7 días.
 */
final class OperationReportGenerator
{
    public const DISK = 'local';

    public const DIRECTORY = 'operation-reports';

    /**
     * Por encima de este número de filas, el detalle en CSV/Excel se genera en cola.
     */
    public const SYNC_ROW_LIMIT = 3000;

    /**
     * Tope de filas del detalle en PDF: más filas no se leen en papel.
     */
    public const PDF_ROW_LIMIT = 1000;

    public const RETENTION_DAYS = 7;

    public const RECENT_LIMIT = 6;

    /**
     * Nombre de archivo permitido en descargas: evita recorrer carpetas.
     */
    public const FILENAME_PATTERN = '/^reporte-operaciones-[a-z0-9-]+\.(xlsx|csv|pdf)$/';

    /**
     * Los resúmenes cortos siempre son instantáneos. Va a la cola el PDF de
     * cualquier reporte que pueda traer cientos de filas (DomPDF tarda segundos
     * y cientos de MB en maquetarlas) y el detalle grande en Excel/CSV.
     */
    public static function shouldQueue(OperationReportType $type, OperationReportFormat $format, int $rowCount): bool
    {
        if ($format === OperationReportFormat::Pdf) {
            return $type->hasManyRows();
        }

        return $type->isDetail() && $rowCount > self::SYNC_ROW_LIMIT;
    }

    public static function filename(OperationReportType $type, OperationReportFormat $format, ?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return 'reporte-operaciones-'.$type->fileSlug().'-'.$now->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.'.$format->extension();
    }

    public static function userDirectory(int $userId): string
    {
        return self::DIRECTORY.'/'.$userId;
    }

    /**
     * Genera el reporte y lo deja en la carpeta del usuario. Idempotente por
     * nombre de archivo: un reintento del job reescribe el mismo archivo.
     */
    public static function store(
        OperationReportType $type,
        OperationReportFormat $format,
        OperationReportFilters $filters,
        int $userId,
        string $filename,
    ): string {
        if (! $type->supportsFormat($format)) {
            throw new \InvalidArgumentException('El reporte «'.$type->label().'» no está disponible en '.$format->label().'.');
        }

        $disk = self::disk();
        $relativePath = self::userDirectory($userId).'/'.$filename;
        $disk->makeDirectory(self::userDirectory($userId));

        $rowLimit = $format === OperationReportFormat::Pdf && $type->hasManyRows() ? self::PDF_ROW_LIMIT : null;
        $data = OperationReportBuilder::build($type, $filters, $rowLimit);
        $notice = $rowLimit === null ? null : self::pdfLimitNotice($type, $filters, $data);

        $absolutePath = $disk->path($relativePath);
        $partialPath = $absolutePath.'.part';

        try {
            OperationReportWriter::write($data, $format, $partialPath, $notice);
            rename($partialPath, $absolutePath);
        } catch (Throwable $exception) {
            @unlink($partialPath);

            throw $exception;
        }

        self::purgeExpired($userId);

        return $relativePath;
    }

    /**
     * Aviso del PDF cuando el tope deja filas fuera. En los resúmenes por
     * paciente las filas son pacientes; en el detalle, servicios.
     */
    private static function pdfLimitNotice(OperationReportType $type, OperationReportFilters $filters, OperationReportData $data): ?string
    {
        $total = $data->totalRows ?? OperationReportBuilder::count($type, $filters);

        if ($total <= self::PDF_ROW_LIMIT) {
            return null;
        }

        $unit = $type->isDetail() ? 'servicios' : 'pacientes';

        return 'El PDF muestra los primeros '.number_format(self::PDF_ROW_LIMIT, 0, ',', '.')
            .' de '.number_format($total, 0, ',', '.').' '.$unit
            .'. Para el listado completo descargue el reporte en Excel o CSV.';
    }

    public static function absolutePathFor(int $userId, string $filename): ?string
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return null;
        }

        $relativePath = self::userDirectory($userId).'/'.$filename;

        return self::disk()->exists($relativePath) ? self::disk()->path($relativePath) : null;
    }

    public static function exists(int $userId, string $filename): bool
    {
        return self::absolutePathFor($userId, $filename) !== null;
    }

    /**
     * Últimos reportes del usuario, del más reciente al más antiguo.
     *
     * @return list<array{name: string, label: string, size: string, created_at: CarbonImmutable, format: OperationReportFormat|null}>
     */
    public static function recent(int $userId): array
    {
        $disk = self::disk();

        return collect($disk->files(self::userDirectory($userId)))
            ->map(fn (string $path): string => basename($path))
            ->filter(fn (string $name): bool => preg_match(self::FILENAME_PATTERN, $name) === 1)
            ->map(fn (string $name): array => [
                'name' => $name,
                'label' => self::labelFor($name),
                'size' => self::humanSize((int) $disk->size(self::userDirectory($userId).'/'.$name)),
                'created_at' => CarbonImmutable::createFromTimestamp($disk->lastModified(self::userDirectory($userId).'/'.$name)),
                'format' => OperationReportFormat::tryFrom((string) pathinfo($name, PATHINFO_EXTENSION)),
            ])
            ->sortByDesc(fn (array $report): int => $report['created_at']->getTimestamp())
            ->take(self::RECENT_LIMIT)
            ->values()
            ->all();
    }

    public static function labelFor(string $filename): string
    {
        foreach (OperationReportType::cases() as $type) {
            if (str_starts_with($filename, 'reporte-operaciones-'.$type->fileSlug().'-')) {
                return $type->label();
            }
        }

        return 'Reporte de Operaciones';
    }

    public static function purgeExpired(int $userId): void
    {
        $disk = self::disk();
        $limit = CarbonImmutable::now()->subDays(self::RETENTION_DAYS)->getTimestamp();

        foreach ($disk->files(self::userDirectory($userId)) as $path) {
            if ($disk->lastModified($path) < $limit) {
                $disk->delete($path);
            }
        }
    }

    private static function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_048_576 => number_format($bytes / 1_048_576, 1, ',', '.').' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', '.').' KB',
            default => $bytes.' B',
        };
    }

    private static function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }
}
