<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CompanyAssociate;
use App\Models\User;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use App\Support\Companies\CompanyAssociateDocumentsAnalystNotifier;
use App\Support\Companies\CompanyAssociateInclusionQrGenerator;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCompanyAssociateDocumentsAfterVoucherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function __construct(
        public int $associateId,
        public int $requestedByUserId,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(): void
    {
        $associate = CompanyAssociate::query()
            ->with(['company', 'responsible'])
            ->find($this->associateId);

        $user = User::query()->find($this->requestedByUserId);

        if ($associate === null) {
            Log::warning('GenerateCompanyAssociateDocumentsAfterVoucherJob: asociado no encontrado', [
                'associate_id' => $this->associateId,
            ]);

            return;
        }

        if ($user === null) {
            Log::warning('GenerateCompanyAssociateDocumentsAfterVoucherJob: usuario solicitante no encontrado', [
                'associate_id' => $this->associateId,
                'user_id' => $this->requestedByUserId,
            ]);

            return;
        }

        CompanyAssociateInclusionQrGenerator::ensurePublished();

        $carnet = CompanyAssociateCarnetGenerator::generate($associate);

        $associate = $associate->fresh(['company', 'responsible']);

        CompanyAssociateDocumentsAnalystNotifier::notifySuccess($user, $associate, $carnet);

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_GENERATED', 'company-associates.voucher-ils.documents', [
            'associate_id' => $associate->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'carnet_filename' => $carnet['filename'],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $associate = CompanyAssociate::query()
            ->with(['company', 'responsible'])
            ->find($this->associateId);

        $user = User::query()->find($this->requestedByUserId);

        if ($associate === null || $user === null) {
            return;
        }

        CompanyAssociateDocumentsAnalystNotifier::notifyFailure(
            $user,
            $associate,
            $exception?->getMessage() ?? 'Error desconocido',
        );

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_FAILED', 'company-associates.voucher-ils.documents', [
            'associate_id' => $this->associateId,
            'requested_by_user_id' => $this->requestedByUserId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
