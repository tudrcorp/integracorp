<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\MassNotificationRecipientDelivery;
use App\Support\MassNotificationWhatsAppSender;
use DateTimeInterface;
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

    /**
     * Intentos totales permitidos (incluye release por canal ocupado).
     * El espaciado al encolar hace raro el lock timeout; esto cubre campañas concurrentes.
     */
    public int $tries = 100;

    /**
     * Fallos reales (API / RuntimeException). Los release por lock no lanzan excepción.
     */
    public int $maxExceptions = 5;

    /**
     * Debe ser menor que config queue.connections.redis.retry_after (900).
     * Un envío WhatsApp + pace no necesita 16 minutos.
     */
    public int $timeout = 120;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $throttle = max(5, (int) config('mass-notifications.whatsapp_throttle_seconds', 20));

        return [$throttle, $throttle, $throttle * 2];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
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
