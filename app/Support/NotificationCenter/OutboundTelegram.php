<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Models\NotificationDelivery;
use Throwable;

/**
 * Lleva a la bitácora del Centro de notificaciones lo que salió (o no) por Telegram.
 * La maquinaria de Telegram (notification_deliveries, reintentos, respaldo) no cambia.
 */
final class OutboundTelegram
{
    public static function record(NotificationDelivery $delivery, bool $succeeded, ?string $error = null): void
    {
        try {
            $decision = OutboundGuard::evaluate(OutboundChannel::Telegram, (string) $delivery->target_label, [
                'notification_key' => (string) $delivery->notification_key,
                'origin' => 'telegram:'.$delivery->notification_key,
                'recipient_name' => (string) $delivery->target_label,
            ]);

            OutboundGuard::record($decision, $succeeded ? OutboundMessageStatus::Sent : OutboundMessageStatus::Failed, [
                'recipient' => (string) $delivery->target_label,
                'subject' => (string) $delivery->title,
                'body' => (string) $delivery->body,
                'type' => filled($delivery->attachment_path) ? 'document' : 'chat',
                'attachment' => $delivery->attachment_name,
                'error' => $succeeded ? null : $error,
            ]);
        } catch (Throwable) {
        }
    }
}
