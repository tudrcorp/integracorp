<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Affiliation;
use App\Services\AffiliationBusinessDocumentsService;
use App\Support\Viveplus\ViveplusDocumentWebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RegenerateMergedAffiliationDocumentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $affiliationId,
        public ?int $notifiedUserId = null,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(): void
    {
        $affiliation = Affiliation::query()->find($this->affiliationId);

        if (! $affiliation instanceof Affiliation) {
            return;
        }

        try {
            AffiliationBusinessDocumentsService::regenerateCertificateAndTarjetas(
                $affiliation,
                $this->notifiedUserId,
            );
        } catch (Throwable $exception) {
            Log::error('No se pudieron regenerar certificado y carnets tras unificar grupo familiar', [
                'affiliation_id' => $this->affiliationId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        ViveplusDocumentWebhookDispatcher::dispatchForIndividual($affiliation, $this->notifiedUserId);
    }
}
