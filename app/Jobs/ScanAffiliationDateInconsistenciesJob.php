<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Affiliations\DateInconsistencies\DateInconsistencyScanner;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Revisión diaria de inconsistencias de fechas en afiliaciones activas (solo lee las afiliaciones;
 * escribe únicamente la lista de inconsistencias y su bitácora). Corre después de las renovaciones.
 */
class ScanAffiliationDateInconsistenciesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsScheduledExecution;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Inconsistencias de fechas en afiliaciones',
            function (): void {
                $totals = DateInconsistencyScanner::scanAll();

                ScheduledTaskRunReport::addMetric('Afiliaciones revisadas', $totals['affiliations']);
                ScheduledTaskRunReport::addMetric('Inconsistencias nuevas', $totals['opened']);
                ScheduledTaskRunReport::addMetric('Reabiertas', $totals['reopened']);
                ScheduledTaskRunReport::addMetric('Resueltas solas', $totals['resolved']);
                ScheduledTaskRunReport::addMetric('Afiliaciones con error', $totals['failed']);
            },
            'Revisa las fechas de emisión, vigencia, avisos y renovaciones de cada afiliación activa y actualiza la lista de inconsistencias de Administración.',
            [
                '*Inconsistencias nuevas* = casos que aparecen por primera vez.',
                '*Reabiertas* = casos marcados como no inconsistencia cuyos datos cambiaron, o reparados que volvieron a aparecer.',
                '*Resueltas solas* = casos abiertos que ya no se detectan.',
            ],
        );
    }
}
