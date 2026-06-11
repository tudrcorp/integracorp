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
        $this->runWithScheduledReport('Caducidad de órdenes de servicio', function (): void {
            $expiredCount = OperationServiceOrderValidity::expireEligibleOrders('system');

            ScheduledTaskRunReport::addMetric('Órdenes caducadas', $expiredCount);
        });
    }
}
