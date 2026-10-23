<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Genera y guarda los reportes del Centro de Reportes de Administración en el
 * disco privado.
 *
 * Cada usuario tiene su carpeta (`administration-reports/{id}`) y solo descarga
 * lo suyo por una ruta autenticada. Los archivos viven 7 días.
 */
final class AdministrationReportGenerator
{
    public const DISK = 'local';

    public const DIRECTORY = 'administration-reports';

    /**
     * Hasta este número de afiliaciones, Excel y CSV se descargan al instante.
     */
    public const SYNC_ROW_LIMIT = 2000;

    /**
     * Hasta este número de afiliaciones, el PDF se descarga al instante: DomPDF
     * tarda segundos y cientos de MB en maquetar más.
     */
    public const PDF_SYNC_ROW_LIMIT = 300;

    /**
     * Tope de filas del PDF: más filas no se leen en papel.
     */
    public const PDF_ROW_LIMIT = 1500;

    public const RETENTION_DAYS = 7;

    public const RECENT_LIMIT = 6;

    /**
     * Nombre de archivo permitido en descargas: evita recorrer carpetas.
     */
    public const FILENAME_PATTERN = '/^reporte-administracion-[a-z0-9-]+\.(xlsx|csv|pdf)$/';

    public static function shouldQueue(OperationReportFormat $format, int $rowCount): bool
    {
        return $rowCount > ($format === OperationReportFormat::Pdf ? self::PDF_SYNC_ROW_LIMIT : self::SYNC_ROW_LIMIT);
    }

    public static function filename(AdministrationReportType $type, OperationReportFormat $format, ?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return 'reporte-administracion-'.$type->fileSlug().'-'.$now->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.'.$format->extension();
    }

    public static function userDirectory(int $userId): string
    {
        return self::DIRECTORY.'/'.$userId;
    }

    /**
     * Genera el reporte y lo deja en la carpeta del usuario. Idempotente por
     * nombre de archivo: un reintento del job reescribe el mismo archivo. Se
     * escribe a un `.part` y se renombra al final, así nunca se descarga a medias.
     */
    public static function store(OperationReportFormat $format, CollectionReportFilters $filters, int $userId, string $filename): string
    {
        $disk = self::disk();
        $relativePath = self::userDirectory($userId).'/'.$filename;
        $disk->makeDirectory(self::userDirectory($userId));

        $rowLimit = $format === OperationReportFormat::Pdf ? self::PDF_ROW_LIMIT : null;
        $data = CollectionReportBuilder::build($filters, $rowLimit);
        $notice = $rowLimit === null ? null : self::pdfLimitNotice($filters);

        $absolutePath = $disk->path($relativePath);
        $partialPath = $absolutePath.'.part';

        try {
            CollectionReportWriter::write($data, $format, $partialPath, $notice);
            rename($partialPath, $absolutePath);
        } catch (Throwable $exception) {
            @unlink($partialPath);

            throw $exception;
        }

        self::purgeExpired($userId);

        return $relativePath;
    }

    private static function pdfLimitNotice(CollectionReportFilters $filters): ?string
    {
        $total = CollectionReportBuilder::count($filters);

        if ($total <= self::PDF_ROW_LIMIT) {
            return null;
        }

        return 'El PDF muestra las primeras '.number_format(self::PDF_ROW_LIMIT, 0, ',', '.')
            .' de '.number_format($total, 0, ',', '.').' '.$filters->type->rowNoun()[1].' y sus totales corresponden solo a esas filas.'
            .' Para el listado completo descargue el reporte en Excel o CSV.';
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
        foreach (AdministrationReportType::cases() as $type) {
            if (str_starts_with($filename, 'reporte-administracion-'.$type->fileSlug().'-')) {
                return $type->label();
            }
        }

        return 'Reporte de Administración';
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
