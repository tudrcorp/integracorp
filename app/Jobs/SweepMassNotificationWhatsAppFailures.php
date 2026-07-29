<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DataNotification;
use App\Models\MassNotification;
use App\Support\MassNotificationRecipientDelivery;
use App\Support\MassNotificationWhatsAppJobScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SweepMassNotificationWhatsAppFailures implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $massNotificationId,
    ) {}

    public function handle(): void
    {
        $notification = MassNotification::query()->find($this->massNotificationId);

        if ($notification === null) {
            Log::warning('SweepMassNotificationWhatsAppFailures: notificación no encontrada', [
                'mass_notification_id' => $this->massNotificationId,
            ]);

            return;
        }

        if (! in_array('whatsapp', (array) $notification->channels, true)) {
            return;
        }

        $recipients = DataNotification::query()
            ->where('mass_notification_id', $this->massNotificationId)
            ->whereIn('whatsapp_status', ['pending', 'failed'])
            ->orderBy('id')
            ->get();

        if ($recipients->isEmpty()) {
            Log::info('SweepMassNotificationWhatsAppFailures: sin pendientes/fallidos', [
                'mass_notification_id' => $this->massNotificationId,
            ]);

            return;
        }

        $infoNotificationArray = $notification->toArray();
        $retryJobs = [];
        $skippedEmpty = 0;

        foreach ($recipients as $recipient) {
            $phone = trim((string) ($recipient->phone ?? ''));

            if ($phone === '') {
                MassNotificationRecipientDelivery::markWhatsappFailed(
                    $recipient->id,
                    'Teléfono vacío o no disponible',
                );
                $skippedEmpty++;

                continue;
            }

            MassNotificationRecipientDelivery::markWhatsappPending($recipient->id);

            $payload = $recipient->toArray();
            $payload['phone'] = $phone;

            $retryJobs[] = new SendNotificationMasive(
                $payload,
                $infoNotificationArray,
                $recipient->id,
            );
        }

        if ($retryJobs === []) {
            Log::info('SweepMassNotificationWhatsAppFailures: finalizado sin reencolar', [
                'mass_notification_id' => $this->massNotificationId,
                'skipped_empty' => $skippedEmpty,
            ]);

            return;
        }

        $staggeredJobs = MassNotificationWhatsAppJobScheduler::withStaggeredDelays($retryJobs);

        Bus::batch($staggeredJobs)
            ->name('mass-notification-whatsapp-sweep-'.$this->massNotificationId)
            ->onQueue('system')
            ->allowFailures()
            ->dispatch();

        Log::info('SweepMassNotificationWhatsAppFailures: reencolados con delays escalonados', [
            'mass_notification_id' => $this->massNotificationId,
            'requeued' => count($staggeredJobs),
            'skipped_empty' => $skippedEmpty,
            'throttle_seconds' => MassNotificationWhatsAppJobScheduler::throttleSeconds(),
        ]);
    }
}
