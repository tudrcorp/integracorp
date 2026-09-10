<?php

namespace App\Jobs;

use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\Affiliations\Concerns\LogsAffiliationJobFailures;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateCorporateCertificateJob implements ShouldQueue
{
    use Batchable, LogsAffiliationJobFailures, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public string $affiliationCode,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(): void
    {
        $this->runWithAffiliationFailureLogging(function (): void {
            // Un padrón de ~2.700 afiliados roza los 900 MB al paginar el certificado completo.
            ini_set('memory_limit', '2048M');
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
        }, $this->affiliationJobFailureContext());
    }

    public function failed(?Throwable $exception): void
    {
        $this->logAffiliationJobFailure($exception, $this->affiliationJobFailureContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliationJobFailureContext(): array
    {
        return [
            'action' => 'regenerate-documents',
            'document_kind' => 'certificate',
            'affiliation_code' => $this->affiliationCode,
        ];
    }
}
