<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SupplierReportPdfService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deja listo en disco el PDF del reporte de proveedores para que la vista previa, la descarga,
 * el correo y la zona de descarga solo lo lean.
 *
 * Único hasta que empieza a procesarse: varias ediciones seguidas encolan un solo job, y una
 * edición que llega mientras corre encola el siguiente, así el archivo nunca queda atrasado.
 * Idempotente: si el PDF de la versión vigente ya existe, no hace nada.
 */
class RegenerateSupplierReportPdfJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $limit = config('supplier-report.pdf_memory_limit', '512M');

        if (is_string($limit) && $limit !== '' && $limit !== '0') {
            @ini_set('memory_limit', $limit);
        }

        SupplierReportPdfService::regenerateStoredFile();
    }

    /**
     * Encola la regeneración tras el commit y con un pequeño retraso para agrupar ediciones.
     */
    public static function dispatchDebounced(): void
    {
        self::dispatch()
            ->onQueue('system')
            ->delay(now()->addSeconds(max(0, (int) config('supplier-report.regenerate_delay_seconds', 60))))
            ->afterCommit();
    }
}
