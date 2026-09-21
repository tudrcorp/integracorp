<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Operations\OperationServiceStatisticSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackfillOperationServiceStatisticsChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  list<int>  $caseIds
     */
    public function __construct(
        public array $caseIds,
    ) {
        $this->onQueue('system');
    }

    public function handle(): void
    {
        if (! OperationServiceStatisticSync::tableReady()) {
            return;
        }

        foreach ($this->caseIds as $caseId) {
            $id = (int) $caseId;
            if ($id < 1) {
                continue;
            }

            try {
                OperationServiceStatisticSync::syncFromCaseId($id);
            } catch (Throwable $exception) {
                Log::error('BackfillOperationServiceStatisticsChunkJob: fallo al sincronizar el caso', [
                    'telemedicine_case_id' => $id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
