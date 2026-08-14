<?php

declare(strict_types=1);

namespace App\Support\Viveplus;

use App\Enums\ViveplusDocumentType;
use App\Exceptions\ViveplusDocumentWebhookPermanentException;
use App\Exceptions\ViveplusDocumentWebhookTransientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ViveplusDocumentWebhookClient
{
    public static function isConfigured(): bool
    {
        return self::webhookUrl() !== ''
            && self::token() !== ''
            && self::signingSecret() !== '';
    }

    public function send(ViveplusDocumentWebhookPayload $payload): ViveplusDocumentWebhookResult
    {
        $this->assertReadyToSend($payload);

        $checksum = hash_file('sha256', $payload->absolutePath);

        if (! is_string($checksum) || strlen($checksum) !== 64) {
            throw new ViveplusDocumentWebhookPermanentException(
                'No se pudo calcular el checksum SHA-256 del PDF.',
            );
        }

        $fields = $payload->fields($checksum);
        $signature = ViveplusDocumentWebhookSigner::signature(
            ViveplusDocumentWebhookSigner::canonicalString($fields),
            self::signingSecret(),
        );

        try {
            $response = Http::timeout(self::timeout())
                ->connectTimeout(self::timeout())
                ->withToken(self::token())
                ->withHeaders([
                    'X-Signature' => $signature,
                ])
                ->attach(
                    'file',
                    (string) file_get_contents($payload->absolutePath),
                    basename($payload->absolutePath),
                    ['Content-Type' => 'application/pdf'],
                )
                ->post(self::webhookUrl(), $fields);
        } catch (ConnectionException $exception) {
            throw new ViveplusDocumentWebhookTransientException(
                'Timeout o error de red al entregar el documento a ViVEplus.',
                0,
                $exception,
            );
        }

        $result = ViveplusDocumentWebhookResult::fromResponse($response);

        $this->logOutcome($payload, $checksum, $result);

        if ($result->accepted) {
            return $result;
        }

        if ($result->status === 401) {
            throw new ViveplusDocumentWebhookPermanentException(
                'Token o firma inválidos al entregar el documento a ViVEplus.',
                401,
            );
        }

        if ($result->status === 422) {
            throw new ViveplusDocumentWebhookPermanentException(
                'Payload inválido al entregar el documento a ViVEplus.',
                422,
                $result->errors,
            );
        }

        if ($result->retryable) {
            throw new ViveplusDocumentWebhookTransientException(
                "ViVEplus respondió HTTP {$result->status} al entregar el documento.",
                $result->status,
            );
        }

        throw new ViveplusDocumentWebhookPermanentException(
            "ViVEplus respondió HTTP {$result->status} al entregar el documento.",
            $result->status,
            $result->errors,
        );
    }

    private function assertReadyToSend(ViveplusDocumentWebhookPayload $payload): void
    {
        if (! self::isConfigured()) {
            throw new ViveplusDocumentWebhookPermanentException(
                'El webhook de ViVEplus no está configurado (URL, token o secreto vacíos).',
            );
        }

        $url = self::webhookUrl();

        if (! str_starts_with($url, 'https://')) {
            throw new ViveplusDocumentWebhookPermanentException(
                'El webhook de ViVEplus debe usar HTTPS.',
            );
        }

        if (! is_file($payload->absolutePath)) {
            throw new ViveplusDocumentWebhookPermanentException(
                'El PDF local no existe; no se envía a ViVEplus para no perder la referencia.',
            );
        }

        $size = filesize($payload->absolutePath);

        if ($size === false || $size > self::maxFileBytes()) {
            throw new ViveplusDocumentWebhookPermanentException(
                'El PDF supera el máximo de 10 MB permitido por ViVEplus.',
            );
        }

        if (
            $payload->documentType === ViveplusDocumentType::Carnet
            && trim($payload->affiliateIdentification) === ''
        ) {
            throw new ViveplusDocumentWebhookPermanentException(
                'affiliate_identification es obligatorio cuando document_type=carnet.',
                422,
            );
        }
    }

    private function logOutcome(
        ViveplusDocumentWebhookPayload $payload,
        string $checksum,
        ViveplusDocumentWebhookResult $result,
    ): void {
        $context = [
            'affiliation_type' => $payload->affiliationType->value,
            'affiliation_code' => $payload->affiliationCode,
            'document_type' => $payload->documentType->value,
            'affiliate_identification' => $payload->affiliateIdentification,
            'idempotency_key' => $payload->idempotencyKey,
            'checksum_sha256' => $checksum,
            'generated_at' => $payload->generatedAt,
            'status' => $result->status,
        ];

        if ($result->accepted) {
            Log::info('Viveplus document webhook: documento aceptado', $context);

            return;
        }

        if ($result->status === 422) {
            Log::error('Viveplus document webhook: payload inválido', [
                ...$context,
                'errors' => $result->errors,
            ]);

            return;
        }

        Log::error('Viveplus document webhook: entrega rechazada', $context);
    }

    public static function webhookUrl(): string
    {
        return rtrim(trim((string) config('services.viveplus_documents.webhook_url')), '/');
    }

    public static function token(): string
    {
        return trim((string) config('services.viveplus_documents.token'));
    }

    public static function signingSecret(): string
    {
        return trim((string) config('services.viveplus_documents.signing_secret'));
    }

    public static function timeout(): int
    {
        return max(1, (int) config('services.viveplus_documents.timeout', 15));
    }

    public static function maxFileBytes(): int
    {
        return max(1, (int) config('services.viveplus_documents.max_file_bytes', 10485760));
    }

    public static function laterRetryDelaySeconds(): int
    {
        return max(1, (int) config('services.viveplus_documents.later_retry_delay_seconds', 120));
    }

    public static function maxLaterRetries(): int
    {
        return max(0, (int) config('services.viveplus_documents.max_later_retries', 5));
    }
}
