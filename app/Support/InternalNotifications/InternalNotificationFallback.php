<?php

declare(strict_types=1);

namespace App\Support\InternalNotifications;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\SystemNotificationKey;
use App\Enums\TelegramDeliveryMode;
use App\Enums\TelegramRecipientType;
use App\Models\NotificationDelivery;
use App\Models\TelegramRecipient;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\DB;

/**
 * Respaldo de una entrega por Telegram que no se pudo completar.
 *
 * - Persona: WhatsApp a su teléfono; correo solo si no tiene teléfono.
 * - Grupo: WhatsApp a los teléfonos del aviso; correo solo si no hay teléfonos.
 *
 * Telegram reemplaza al WhatsApp, no al correo: los correos del aviso ya salieron
 * por su cuenta, así que el respaldo no los repite.
 *   En modo «Ambos» se salta a quien ya tiene su propio Telegram vinculado.
 *
 * Idempotente: una entrega solo pasa al respaldo una vez.
 */
final class InternalNotificationFallback
{
    /**
     * @return int cantidad de envíos de respaldo encolados
     */
    public static function run(int $deliveryId, string $reason, bool $sync = false): int
    {
        $queued = [];

        DB::transaction(function () use ($deliveryId, $reason, &$queued): void {
            $delivery = NotificationDelivery::query()->with('recipient.user')->lockForUpdate()->find($deliveryId);

            if (! $delivery instanceof NotificationDelivery || $delivery->status !== NotificationDeliveryStatus::Pending) {
                return;
            }

            $notification = new InternalNotification(
                SystemNotificationKey::tryFrom($delivery->notification_key) ?? SystemNotificationKey::TelegramTest,
                (string) $delivery->title,
                (string) $delivery->body,
                (bool) $delivery->is_critical,
                $delivery->created_by,
                false,
                true,
                filled($delivery->attachment_path) || filled($delivery->attachment_url)
                    ? new InternalAttachment((string) $delivery->attachment_path, (string) ($delivery->attachment_name ?: 'documento.pdf'), $delivery->attachment_url)
                    : null,
            );

            foreach (self::targets($delivery) as [$channel, $target, $label]) {
                $queued[] = InternalNotificationDispatcher::record($notification, $channel, NotificationDeliveryStatus::Queued, $label, null, null, $target, $delivery);
            }

            $delivery->forceFill([
                'status' => $queued === [] ? NotificationDeliveryStatus::Failed : NotificationDeliveryStatus::FellBack,
                'error' => mb_substr($reason.($queued === [] ? ' · Sin teléfono ni correo para el respaldo.' : ''), 0, 1000),
            ])->save();
        });

        foreach ($queued as $child) {
            InternalNotificationDispatcher::sendDirect($child, true, $sync);
        }

        return count($queued);
    }

    /**
     * @return list<array{0: NotificationDeliveryChannel, 1: string, 2: string}>
     */
    private static function targets(NotificationDelivery $delivery): array
    {
        $recipient = $delivery->recipient;

        if ($recipient instanceof TelegramRecipient && $recipient->type->isPerson()) {
            $phone = $recipient->phone ?: $recipient->user?->phone;
            $email = $recipient->email ?: $recipient->user?->email;
            $targets = [];

            if (filled($phone)) {
                $targets[] = [NotificationDeliveryChannel::WhatsApp, (string) $phone, $recipient->name];
            }

            /** El correo del aviso ya salió por su cuenta: aquí solo si no hay teléfono. */
            if (filled($email) && blank($phone)) {
                $targets[] = [NotificationDeliveryChannel::Mail, (string) $email, $recipient->name];
            }

            return $targets;
        }

        $key = SystemNotificationKey::tryFrom($delivery->notification_key);

        if ($key === null || ($recipient instanceof TelegramRecipient && $recipient->type !== TelegramRecipientType::Group)) {
            return [];
        }

        $setting = SystemNotificationRecipients::setting($key);
        $skipLinked = $setting->telegramMode() === TelegramDeliveryMode::Both;
        $contacts = InternalContactResolver::resolve($setting, $skipLinked);
        $targets = [];

        foreach ($contacts['phones'] as $phone) {
            $targets[] = [NotificationDeliveryChannel::WhatsApp, $phone, $phone];
        }

        if ($contacts['phones'] === []) {
            foreach ($contacts['emails'] as $email) {
                $targets[] = [NotificationDeliveryChannel::Mail, $email, $email];
            }
        }

        return $targets;
    }
}
