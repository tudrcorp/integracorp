<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DataNotification;
use App\Models\MassNotification;
use App\Support\MassNotificationRecipientDelivery;
use App\Support\MassNotificationWhatsAppSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SweepMassNotificationWhatsAppFailures implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

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
        $retried = 0;
        $recovered = 0;
        $stillFailed = 0;

        foreach ($recipients as $recipient) {
            $retried++;

            MassNotificationRecipientDelivery::markWhatsappPending($recipient->id);

            $result = MassNotificationWhatsAppSender::send(
                $recipient->toArray(),
                $infoNotificationArray,
                throttle: true,
            );

            if ($result->success) {
                MassNotificationRecipientDelivery::markWhatsappSent($recipient->id);
                $recovered++;

                continue;
            }

            MassNotificationRecipientDelivery::markWhatsappFailed(
                $recipient->id,
                $result->errorMessage ?? 'No se pudo reenviar por WhatsApp',
            );
            $stillFailed++;
        }

        Log::info('SweepMassNotificationWhatsAppFailures: finalizado', [
            'mass_notification_id' => $this->massNotificationId,
            'retried' => $retried,
            'recovered' => $recovered,
            'still_failed' => $stillFailed,
        ]);
    }
}
