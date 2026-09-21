<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ViveplusAffiliationType;
use App\Enums\ViveplusDocumentType;
use App\Exceptions\ViveplusDocumentWebhookPermanentException;
use App\Models\Affiliation;
use App\Support\Affiliations\Concerns\LogsAffiliationJobFailures;
use App\Support\AffiliationWhiteCompany;
use App\Support\Viveplus\ViveplusDocumentWebhookAnalystNotifier;
use App\Support\Viveplus\ViveplusDocumentWebhookClient;
use App\Support\Viveplus\ViveplusDocumentWebhookPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushAffiliationDocumentToViveplusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, LogsAffiliationJobFailures, Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function __construct(
        public string $affiliationType,
        public string $affiliationCode,
        public string $documentType,
        public string $absolutePath,
        public string $generatedAt,
        public string $idempotencyKey,
        public ?int $notifiedUserId = null,
        public int $laterRetryRound = 0,
        public string $affiliateIdentification = '',
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(ViveplusDocumentWebhookClient $client): void
    {
        $this->runWithAffiliationFailureLogging(function () use ($client): void {
            if ($this->shouldSkipBecauseAffiliationIsNotAllied()) {
                Log::info('Viveplus document webhook: omitido; la afiliación individual no pertenece a una empresa aliada', [
                    'affiliation_code' => $this->affiliationCode,
                    'document_type' => $this->documentType,
                ]);

                return;
            }

            try {
                $client->send($this->payload());
            } catch (ViveplusDocumentWebhookPermanentException $exception) {
                $this->fail($exception);
            }
        }, $this->affiliationJobFailureContext());
    }

    private function shouldSkipBecauseAffiliationIsNotAllied(): bool
    {
        if ($this->affiliationType !== ViveplusAffiliationType::Individual->value) {
            return false;
        }

        $affiliation = Affiliation::query()
            ->with('whiteCompanyUser')
            ->where('code', $this->affiliationCode)
            ->first();

        if ($affiliation === null) {
            return false;
        }

        return ! AffiliationWhiteCompany::belongsToAlliedCompany($affiliation);
    }

    public function failed(?Throwable $exception): void
    {
        $willRetryLater = ! ($exception instanceof ViveplusDocumentWebhookPermanentException)
            && $this->laterRetryRound < ViveplusDocumentWebhookClient::maxLaterRetries();

        $this->logAffiliationJobFailure($exception, array_merge($this->affiliationJobFailureContext(), [
            'will_retry_later' => $willRetryLater,
            'later_retries_exhausted' => ! $willRetryLater && ! ($exception instanceof ViveplusDocumentWebhookPermanentException),
        ]));

        $notifier = app(ViveplusDocumentWebhookAnalystNotifier::class);
        $reason = $exception instanceof Throwable
            ? $notifier->reasonFromException($exception)
            : 'Error desconocido al entregar el documento a ViVEplus.';

        $notifier->notifyDeliveryFailed(
            $this->notifiedUserId,
            $this->affiliationCode,
            $this->documentType,
            $reason,
        );

        if ($exception instanceof ViveplusDocumentWebhookPermanentException) {
            return;
        }

        if ($this->laterRetryRound >= ViveplusDocumentWebhookClient::maxLaterRetries()) {
            return;
        }

        self::dispatch(
            $this->affiliationType,
            $this->affiliationCode,
            $this->documentType,
            $this->absolutePath,
            $this->generatedAt,
            $this->idempotencyKey,
            $this->notifiedUserId,
            $this->laterRetryRound + 1,
            $this->affiliateIdentification,
        )->delay(now()->addSeconds(ViveplusDocumentWebhookClient::laterRetryDelaySeconds()));
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliationJobFailureContext(): array
    {
        return [
            'action' => 'viveplus-document-webhook',
            'affiliation_type' => $this->affiliationType,
            'affiliation_code' => $this->affiliationCode,
            'document_type' => $this->documentType,
            'absolute_path' => $this->absolutePath,
            'document_exists' => is_file($this->absolutePath),
            'generated_at' => $this->generatedAt,
            'idempotency_key' => $this->idempotencyKey,
            'notified_user_id' => $this->notifiedUserId,
            'later_retry_round' => $this->laterRetryRound,
            'affiliate_identification' => $this->affiliateIdentification,
        ];
    }

    public function payload(): ViveplusDocumentWebhookPayload
    {
        return new ViveplusDocumentWebhookPayload(
            affiliationType: ViveplusAffiliationType::from($this->affiliationType),
            affiliationCode: $this->affiliationCode,
            documentType: ViveplusDocumentType::from($this->documentType),
            absolutePath: $this->absolutePath,
            generatedAt: $this->generatedAt,
            idempotencyKey: $this->idempotencyKey,
            affiliateIdentification: $this->affiliateIdentification,
        );
    }
}
