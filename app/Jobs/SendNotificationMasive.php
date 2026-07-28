<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\MassNotificationRecipientDelivery;
use App\Support\MassNotificationWhatsAppSender;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendNotificationMasive implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 25;

    public int $timeout = 960;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $throttle = max(5, (int) config('mass-notifications.whatsapp_throttle_seconds', 20));

        return [$throttle, $throttle, $throttle * 2];
    }

    /**
     * @param  array<string, mixed>  $dataNotificationArray
     * @param  array<string, mixed>  $infoNotificationArray
     */
    public function __construct(
        protected array $dataNotificationArray,
        protected array $infoNotificationArray,
        protected int $dataNotificationId,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $result = MassNotificationWhatsAppSender::send(
                $this->dataNotificationArray,
                $this->infoNotificationArray,
                throttle: true,
            );
        } catch (LockTimeoutException) {
            $releaseIn = max(5, (int) config('mass-notifications.whatsapp_throttle_seconds', 20));
            $this->release($releaseIn);

            return;
        }

        if (! $result->success) {
            throw new RuntimeException($result->errorMessage ?? 'No se pudo enviar la notificación por WhatsApp.');
        }

        MassNotificationRecipientDelivery::markWhatsappSent($this->dataNotificationId);
    }

    public function failed(?Throwable $exception): void
    {
        MassNotificationRecipientDelivery::markWhatsappFailed(
            $this->dataNotificationId,
            $exception?->getMessage() ?? 'Error desconocido en el job de WhatsApp',
        );

        Log::info('SendNotificationMasive: FAILED', [
            'data_notification_id' => $this->dataNotificationId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
