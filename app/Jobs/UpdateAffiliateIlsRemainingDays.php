<?php

namespace App\Jobs;

use App\Models\Affiliate;
use App\Support\AffiliateVaucherIlsRemainingDays;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateAffiliateIlsRemainingDays implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Afiliados ILS — días restantes',
            function (): void {
                $today = Carbon::today();
                $processed = 0;
                $updated = 0;
                $skippedInvalidDate = 0;
                $unchanged = 0;

                ScheduledTaskRunReport::addExecutionDetail('Alcance', 'Afiliados con dateEnd definido');
                ScheduledTaskRunReport::addExecutionDetail('Fecha de cálculo', $today->format('d/m/Y'));

                Affiliate::query()
                    ->whereNotNull('dateEnd')
                    ->select(['id', 'dateEnd', 'numberDays'])
                    ->chunkById(500, function ($affiliates) use ($today, &$processed, &$updated, &$skippedInvalidDate, &$unchanged): void {
                        foreach ($affiliates as $affiliate) {
                            $processed++;
                            $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($affiliate->dateEnd, $today);

                            if ($days === null) {
                                $skippedInvalidDate++;

                                continue;
                            }

                            if ((int) $affiliate->numberDays === $days) {
                                $unchanged++;

                                continue;
                            }

                            $affiliate->update([
                                'numberDays' => $days,
                            ]);
                            $updated++;
                        }
                    });

                ScheduledTaskRunReport::addMetric('Afiliados procesados', $processed);
                ScheduledTaskRunReport::addMetric('Afiliados actualizados', $updated);
                ScheduledTaskRunReport::addMetric('Sin cambios', $unchanged);
                ScheduledTaskRunReport::addMetric('Fecha ILS inválida', $skippedInvalidDate);

                Log::info('UpdateAffiliateIlsRemainingDays: OK', [
                    'processed' => $processed,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'skipped_invalid_date' => $skippedInvalidDate,
                    'date' => $today->toDateString(),
                ]);
            },
            'Actualiza numberDays de afiliados ILS según los días restantes hasta dateEnd.',
            [
                '*Afiliados procesados* = registros con dateEnd evaluados.',
                '*Afiliados actualizados* = numberDays cambió respecto al valor guardado.',
                '*Sin cambios* = el valor calculado ya coincidía.',
                '*Fecha ILS inválida* = dateEnd no pudo interpretarse; no se actualiza el registro.',
            ],
        );
    }
}
