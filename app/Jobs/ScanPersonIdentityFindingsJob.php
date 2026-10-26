<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Affiliations\PersonIdentity\PersonIdentityScanner;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Revisión diaria de personas duplicadas entre afiliados individuales, corporativos y Nuevos
 * Negocios. Recalcula las claves de identidad y escribe únicamente la lista de casos y su bitácora.
 */
class ScanPersonIdentityFindingsJob implements ShouldQueue
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
            'Personas duplicadas en afiliaciones',
            function (): void {
                $totals = PersonIdentityScanner::scanAll();

                ScheduledTaskRunReport::addMetric('Personas vigentes revisadas', $totals['people']);
                ScheduledTaskRunReport::addMetric('Casos nuevos', $totals['opened']);
                ScheduledTaskRunReport::addMetric('Reabiertos', $totals['reopened']);
                ScheduledTaskRunReport::addMetric('Resueltos solos', $totals['resolved']);
                ScheduledTaskRunReport::addMetric('Claves de identidad actualizadas', $totals['keys_updated']);
            },
            'Busca personas registradas dos veces (misma cédula y nombre, o mismo nombre y nacimiento) entre afiliados individuales, corporativos y Nuevos Negocios, y nombres cargados dos veces.',
            [
                '*Casos nuevos* = duplicados que aparecen por primera vez.',
                '*Reabiertos* = casos marcados como no duplicado cuyos datos cambiaron, o corregidos que volvieron a aparecer.',
                '*Claves de identidad actualizadas* = registros escritos sin pasar por el sistema (cargas por lote) cuya identidad se recalculó.',
            ],
        );
    }
}
