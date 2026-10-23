<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights;

use App\Support\Administration\Reports\AdministrationReportGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * PDF del Informe administrativo. Se guarda en la misma carpeta privada y con el mismo
 * patrón de nombre que los reportes del Centro de reportes, así se descarga por la misma
 * ruta autenticada y aparece en «Mis reportes recientes».
 */
final class AdministrativeReportPdf
{
    public const FILE_SLUG = 'informe-administrativo';

    public const LABEL = 'Informe administrativo';

    public static function filename(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return 'reporte-administracion-'.self::FILE_SLUG.'-'.$now->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.pdf';
    }

    /**
     * @param  array<string, mixed>  $report  Salida de {@see AdministrativeReportBuilder::build()}.
     */
    public static function render(array $report): string
    {
        return (string) Pdf::loadView('documents.administrative-report', ['report' => $report])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * Genera y guarda el PDF. Idempotente por nombre: un reintento reescribe el mismo
     * archivo. Se escribe a un `.part` y se renombra al final para no servirlo a medias.
     */
    public static function store(AdministrativeReportFilters $filters, int $userId, string $filename): string
    {
        $disk = Storage::disk(AdministrationReportGenerator::DISK);
        $directory = AdministrationReportGenerator::userDirectory($userId);
        $disk->makeDirectory($directory);

        $absolute = $disk->path($directory.'/'.$filename);
        $partial = $absolute.'.part';

        try {
            if (file_put_contents($partial, self::render((new AdministrativeReportBuilder($filters))->build())) === false) {
                throw new RuntimeException('No se pudo guardar el PDF del informe administrativo.');
            }

            rename($partial, $absolute);
        } catch (Throwable $exception) {
            @unlink($partial);

            throw $exception;
        }

        AdministrationReportGenerator::purgeExpired($userId);

        return $directory.'/'.$filename;
    }
}
