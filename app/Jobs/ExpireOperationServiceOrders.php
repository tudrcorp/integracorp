<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireOperationServiceOrders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsScheduledExecution;
    use SerializesModels;

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Caducidad de órdenes de servicio',
            function (): void {
                ScheduledTaskRunReport::addExecutionDetail('Acción', 'Marcar como caducadas las órdenes elegibles según reglas de vigencia');

                $expiredCount = OperationServiceOrderValidity::expireEligibleOrders('system');

                ScheduledTaskRunReport::addMetric('Órdenes caducadas', $expiredCount);
            },
            'Caduca automáticamente las órdenes de servicio de operaciones que cumplieron las condiciones de vencimiento.',
            [
                '*Órdenes caducadas* = registros actualizados a estado caducado en esta ejecución.',
                'Si el valor es 0, no había órdenes pendientes de caducar hoy.',
            ],
        );
    }
}
