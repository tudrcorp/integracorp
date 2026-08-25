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

    public int $timeout = 600;

    public function __construct(
        public string $affiliationCode,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(540);

        $record = AffiliationCorporate::query()
            ->where('code', $this->affiliationCode)
            ->with([
                'corporateAffiliates',
                'affiliationCorporatePlans.plan.benefitPlans',
                'plan.benefitPlans',
                'coverage',
                'agent',
                'agency',
            ])
            ->firstOrFail();

        AffiliationCorporateBusinessDocumentsService::generateCorporateCertificate($record);
    }
}
