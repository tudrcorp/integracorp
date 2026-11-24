<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Models\ServiceOrderPatientNotification as Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entrega del WhatsApp al paciente: los checks de WhatsApp (✓, ✓✓, ✓✓ azul).
 *
 * Llegan de dos maneras:
 * - Por el webhook (`event_type` «message_ack»), en el momento. En UltraMsg hay que tener
 *   activado el aviso «message ack» de la instancia, además de «message received».
 * - Consultando a UltraMsg en la revisión periódica, para lo que el webhook no confirmó.
 *
 * El mensaje se reconoce por el `referenceId` que se manda al enviarlo
 * (`sopn-{id}-a{intento}`), por el id que UltraMsg devolvió o por el id de WhatsApp.
 * Con el intento en la referencia, los checks de un envío anterior no se confunden con los
 * del reenvío. La entrega solo avanza: un aviso repetido o atrasado nunca la hace retroceder.
 */
final class ServiceOrderPatientDeliveryTracker
{
    public const EVENT_MESSAGE_ACK = 'message_ack';

    /** Días después del envío en que todavía se consulta la entrega. */
    public const POLL_DAYS = 3;

    /** Minutos entre dos consultas de la misma notificación. */
    public const POLL_EVERY_MINUTES = 10;

    /** Consultas por revisión periódica. */
    public const POLL_BATCH = 40;

    public static function referenceFor(Notification $notification): string
    {
        return 'sopn-'.$notification->getKey().'-a'.(int) $notification->attempts;
    }

    /**
     * Traduce el check de UltraMsg / WhatsApp al estado de entrega. Admite el texto
     * (pending, server, device, read, played) y el número de WhatsApp (0 a 4).
     */
    public static function normalizeAck(mixed $ack): ?string
    {
        if (! is_scalar($ack)) {
            return null;
        }

        return match (strtolower(trim((string) $ack))) {
            'pending', '0' => Notification::DELIVERY_PENDING,
            'server', 'sent', '1' => Notification::DELIVERY_SERVER,
            'device', 'delivered', '2' => Notification::DELIVERY_DEVICE,
            'read', 'played', 'viewed', '3', '4' => Notification::DELIVERY_READ,
            default => null,
        };
    }

    /**
     * Procesa un aviso «message_ack» del webhook. Devuelve la notificación actualizada, o null
     * si el aviso no es de un WhatsApp al paciente (la instancia avisa de todos sus mensajes).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function handleWebhook(array $payload): ?Notification
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $status = self::normalizeAck($data['ack'] ?? null);

        if ($status === null) {
            return null;
        }

        $whatsAppId = self::scalar($data['id'] ?? null);
        $notification = self::find(
            self::scalar($payload['referenceId'] ?? $data['referenceId'] ?? null),
            self::scalar($payload['id'] ?? null),
            $whatsAppId,
        );

        if (! $notification instanceof Notification) {
            return null;
        }

        return self::apply($notification, $status, $whatsAppId);
    }

    public static function find(?string $referenceId, ?string $ultramsgId, ?string $whatsAppId): ?Notification
    {
        if ($referenceId !== null && preg_match('/^sopn-(\d+)-a(\d+)$/', $referenceId, $matches) === 1) {
            return Notification::query()
                ->whereKey((int) $matches[1])
                ->where('attempts', (int) $matches[2])
                ->first();
        }

        if ($ultramsgId !== null) {
            $byUltramsgId = Notification::query()->where('ultramsg_message_id', $ultramsgId)->latest('id')->first();

            if ($byUltramsgId instanceof Notification) {
                return $byUltramsgId;
            }
        }

        return $whatsAppId !== null
            ? Notification::query()->where('whatsapp_message_id', $whatsAppId)->latest('id')->first()
            : null;
    }

    /**
     * Avanza la entrega (nunca retrocede) y fecha cuándo llegó y cuándo se leyó.
     */
    public static function apply(Notification $notification, string $status, ?string $whatsAppId = null): Notification
    {
        if (! isset(Notification::DELIVERY_RANKS[$status])) {
            return $notification;
        }

        return DB::transaction(static function () use ($notification, $status, $whatsAppId): Notification {
            $locked = Notification::query()->whereKey($notification->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Notification) {
                return $notification;
            }

            $changes = [];

            if ($whatsAppId !== null && blank($locked->whatsapp_message_id)) {
                $changes['whatsapp_message_id'] = mb_substr($whatsAppId, 0, 191);
            }

            if (Notification::deliveryRank($status) > Notification::deliveryRank($locked->delivery_status)) {
                $changes['delivery_status'] = $status;
            }

            $reached = Notification::deliveryRank($status);

            if ($reached >= Notification::deliveryRank(Notification::DELIVERY_DEVICE) && $locked->delivered_at === null) {
                $changes['delivered_at'] = now();
            }

            if ($reached >= Notification::deliveryRank(Notification::DELIVERY_READ) && $locked->read_at === null) {
                $changes['read_at'] = now();
            }

            if ($changes !== []) {
                $locked->forceFill($changes)->save();
            }

            return $locked;
        });
    }

    /**
     * Consulta a UltraMsg la entrega de lo enviado en los últimos días que todavía no se sabe
     * si llegó. Lo llama la revisión periódica; si UltraMsg no responde, se reintenta luego.
     *
     * @return int notificaciones que avanzaron
     */
    public static function pollPending(): int
    {
        if (! UltraMsgClient::configured()) {
            return 0;
        }

        $advanced = 0;

        Notification::query()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(self::POLL_DAYS))
            ->whereNotNull('ultramsg_message_id')
            ->whereNull('delivered_at')
            ->whereNull('read_at')
            ->where(fn ($query) => $query
                ->whereNull('delivery_checked_at')
                ->orWhere('delivery_checked_at', '<', now()->subMinutes(self::POLL_EVERY_MINUTES)))
            ->orderBy('delivery_checked_at')
            ->orderBy('id')
            ->limit(self::POLL_BATCH)
            ->get()
            ->each(static function (Notification $notification) use (&$advanced): void {
                try {
                    $before = Notification::deliveryRank($notification->delivery_status);
                    $status = self::normalizeAck(UltraMsgClient::messageAck((string) $notification->ultramsg_message_id));

                    Notification::query()->whereKey($notification->getKey())->update(['delivery_checked_at' => now()]);

                    if ($status !== null && Notification::deliveryRank(self::apply($notification, $status)->delivery_status) > $before) {
                        $advanced++;
                    }
                } catch (Throwable $exception) {
                    Log::warning('WhatsApp al paciente: no se pudo consultar la entrega.', ['notification_id' => $notification->getKey(), 'error' => $exception->getMessage()]);
                }
            });

        return $advanced;
    }

    private static function scalar(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
