<?php

namespace App\Jobs;

use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCorporateCertificateJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(
        public string $affiliationCode,
    ) {
        $this->onQueue('documents');
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $record = AffiliationCorporate::query()
            ->where('code', $this->affiliationCode)
            ->with(['corporateAffiliates', 'plan.benefitPlans', 'coverage', 'agent', 'agency'])
            ->firstOrFail();

        AffiliationCorporateBusinessDocumentsService::generateCorporateCertificate($record);
    }
}
