<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AffiliationCertificateDelivery;
use App\Services\AffiliationCertificateDeliveryService;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\CertificateDeliveryNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía por correo un certificado del generador (o solo algunos carnets).
 * Reintentable: el servicio no repite el correo a quien ya lo recibió.
 */
class SendAffiliationCertificateDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue(AffiliationCertificateGeneratorService::queue());
    }

    public function handle(AffiliationCertificateDeliveryService $service): void
    {
        $delivery = AffiliationCertificateDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status === AffiliationCertificateDelivery::STATUS_SENT) {
            return;
        }

        $service->deliver($delivery);

        CertificateDeliveryNotifier::sent($delivery->refresh());
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Certificado del generador: no se pudo enviar por correo', [
            'affiliation_certificate_delivery_id' => $this->deliveryId,
            'message' => $exception?->getMessage(),
        ]);

        $delivery = AffiliationCertificateDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status === AffiliationCertificateDelivery::STATUS_SENT) {
            return;
        }

        $delivery->forceFill([
            'status' => AffiliationCertificateDelivery::STATUS_FAILED,
            'error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
        ])->save();

        CertificateDeliveryNotifier::failed($delivery);
    }
}
