<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use App\Support\Companies\CompanyAssociateDocumentsDeliverer;
use App\Support\Companies\CompanyAssociateInclusionQrGenerator;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function __construct(
        public int $associateId,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(): void
    {
        $associate = CompanyAssociate::query()
            ->with(['company', 'responsible'])
            ->find($this->associateId);

        if ($associate === null) {
            Log::warning('GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob: asociado no encontrado', [
                'associate_id' => $this->associateId,
            ]);

            return;
        }

        CompanyAssociateInclusionQrGenerator::ensurePublished();

        $carnet = CompanyAssociateCarnetGenerator::generate($associate);

        CompanyAssociateDocumentsDeliverer::deliver(
            $associate->fresh(['company', 'responsible']),
            $carnet,
            sendWhatsAppImmediately: true,
        );

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_GENERATED', 'company-associates.public-register.documents', [
            'associate_id' => $associate->getKey(),
            'carnet_filename' => $carnet['filename'],
            'flight_date' => $associate->flight_date?->format('Y-m-d'),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob: FAILED', [
            'associate_id' => $this->associateId,
            'message' => $exception?->getMessage(),
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_FAILED', 'company-associates.public-register.documents', [
            'associate_id' => $this->associateId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
