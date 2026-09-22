<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use App\Support\Companies\CompanyAssociateDocumentsDeliverer;
use App\Support\Companies\CompanyAssociateInclusionQrGenerator;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegenerateCompanyAssociateCarnetAfterEditJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $associateId)
    {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(): void
    {
        $associate = CompanyAssociate::query()
            ->with(['company', 'responsible'])
            ->find($this->associateId);

        if (! $associate instanceof CompanyAssociate) {
            Log::warning('RegenerateCompanyAssociateCarnetAfterEditJob: asociado no encontrado', [
                'associate_id' => $this->associateId,
            ]);

            return;
        }

        if ($associate->isAnnulled()) {
            Log::info('RegenerateCompanyAssociateCarnetAfterEditJob: asociado anulado, no se regenera el carnet', [
                'associate_id' => $this->associateId,
            ]);

            return;
        }

        CompanyAssociateInclusionQrGenerator::ensurePublished();

        $carnet = CompanyAssociateCarnetGenerator::generate($associate);

        CompanyAssociateDocumentsDeliverer::deliver(
            $associate->fresh(['company', 'responsible']) ?? $associate,
            $carnet,
            sendWhatsAppImmediately: true,
            includeAnalystRecipients: false,
        );

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_CARNET_REGENERATED', 'company-associates.edit.carnet', [
            'associate_id' => $associate->getKey(),
            'carnet_filename' => $carnet['filename'],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RegenerateCompanyAssociateCarnetAfterEditJob: FAILED', [
            'associate_id' => $this->associateId,
            'message' => $exception?->getMessage(),
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_CARNET_REGENERATION_FAILED', 'company-associates.edit.carnet', [
            'associate_id' => $this->associateId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
