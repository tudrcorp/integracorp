<?php

namespace App\Jobs;

use App\Models\Affiliate;
use App\Support\AffiliateVaucherIlsRemainingDays;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateAffiliateIlsRemainingDays implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today = Carbon::today();
        $processed = 0;
        $updated = 0;

        try {
            Affiliate::query()
                ->whereNotNull('dateEnd')
                ->select(['id', 'dateEnd', 'numberDays'])
                ->chunkById(500, function ($affiliates) use ($today, &$processed, &$updated): void {
                    foreach ($affiliates as $affiliate) {
                        $processed++;
                        $days = AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($affiliate->dateEnd, $today);

                        if ($days === null) {
                            continue;
                        }

                        if ((int) $affiliate->numberDays === $days) {
                            continue;
                        }

                        $affiliate->update([
                            'numberDays' => $days,
                        ]);
                        $updated++;
                    }
                });

            Log::info('UpdateAffiliateIlsRemainingDays: OK', [
                'processed' => $processed,
                'updated' => $updated,
                'date' => $today->toDateString(),
            ]);
        } catch (Throwable $e) {
            Log::error('UpdateAffiliateIlsRemainingDays: error', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
