<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Models\OutboundMessage;
use App\Support\InternalNotifications\InternalContactResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Guardián del Centro de notificaciones: antes de cada mensaje decide si puede salir
 * y, después, lo deja en la bitácora.
 *
 * Reglas:
 * - Tipo pausado o canal apagado para ese tipo: no sale (no aplica a los avisos internos
 *   del Centro, que se pausan y se dirigen con sus propios destinatarios).
 * - Canal apagado en todo el sistema: no sale, salvo que el tipo sea crítico
 *   (claves OTP, alertas de seguridad y de colas, acceso al sistema).
 * - Cualquier error propio: el mensaje sale igual. El guardián nunca tumba un envío.
 */
final class OutboundGuard
{
    /**
     * @param  array<string, mixed>  $context  catalog (clave explícita), notification_key (aviso interno), origin, recipient_name
     */
    public static function evaluate(OutboundChannel $channel, string $recipient, array $context = []): OutboundDecision
    {
        $origin = isset($context['origin']) && is_string($context['origin']) ? $context['origin'] : null;

        try {
            $origin ??= OutboundOrigin::capture();
            [$audience, $recipientName] = self::audienceFor($channel, $recipient);
            $recipientName = is_string($context['recipient_name'] ?? null) ? $context['recipient_name'] : $recipientName;
            $entry = self::resolveEntry($channel, $context, $origin, $audience);

            if ($entry === null) {
                return new OutboundDecision($channel, null, $audience, $origin, null, false, false, $recipientName);
            }

            $critical = (bool) $entry['is_critical'];

            return new OutboundDecision(
                $channel,
                (string) $entry['key'],
                $audience,
                $origin,
                self::blockedReason($channel, $entry, $critical),
                $critical,
                (bool) $entry['is_bulk'],
                $recipientName,
            );
        } catch (Throwable $exception) {
            Log::warning('Centro de notificaciones: el guardián falló; el mensaje sale sin control.', ['error' => $exception->getMessage()]);

            return new OutboundDecision($channel, null, NotificationAudience::External, $origin);
        }
    }

    /**
     * @param  array{recipient?: string|null, recipients_count?: int, subject?: string|null, body?: string|null, type?: string|null, attachment?: string|null, error?: string|null}  $data
     */
    public static function record(OutboundDecision $decision, OutboundMessageStatus $status, array $data): ?int
    {
        if ($decision->bulk) {
            return null;
        }

        try {
            if (! NotificationCatalog::tablesExist()) {
                return null;
            }

            $recipient = trim((string) ($data['recipient'] ?? ''));

            return (int) OutboundMessage::query()->insertGetId([
                'channel' => $decision->channel->value,
                'status' => $status->value,
                'audience' => $decision->audience->value,
                'catalog_key' => $decision->catalogKey,
                'message_type' => isset($data['type']) ? mb_substr((string) $data['type'], 0, 20) : null,
                'recipient' => $recipient !== '' ? mb_substr($recipient, 0, 255) : null,
                'recipient_key' => self::recipientKey($decision->channel, $recipient),
                'recipient_name' => filled($decision->recipientName) ? mb_substr((string) $decision->recipientName, 0, 255) : null,
                'recipients_count' => max(1, (int) ($data['recipients_count'] ?? 1)),
                'subject' => filled($data['subject'] ?? null) ? mb_substr((string) $data['subject'], 0, 255) : null,
                'body' => filled($data['body'] ?? null) ? mb_substr((string) $data['body'], 0, 4000) : null,
                'attachment_name' => filled($data['attachment'] ?? null) ? mb_substr((string) $data['attachment'], 0, 255) : null,
                'error' => filled($data['error'] ?? null) ? mb_substr((string) $data['error'], 0, 1000) : ($decision->blockedReason !== null ? mb_substr($decision->blockedReason, 0, 1000) : null),
                'origin' => $decision->origin,
                'is_critical' => $decision->critical,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Centro de notificaciones: no se pudo escribir la bitácora.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    public static function markStatus(?int $messageId, OutboundMessageStatus $status, ?string $error = null): void
    {
        if ($messageId === null) {
            return;
        }

        try {
            OutboundMessage::query()->whereKey($messageId)->update([
                'status' => $status->value,
                'error' => $error !== null ? mb_substr($error, 0, 1000) : null,
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private static function resolveEntry(OutboundChannel $channel, array $context, ?string $origin, NotificationAudience $audience): ?array
    {
        if (is_string($context['catalog'] ?? null) && ($entry = NotificationCatalog::entry($context['catalog'])) !== null) {
            return $entry;
        }

        if (is_string($context['notification_key'] ?? null) && ($entry = NotificationCatalog::entry('internal:'.$context['notification_key'])) !== null) {
            return $entry;
        }

        if ($origin === null) {
            return null;
        }

        $key = NotificationCatalog::keyForOrigin($origin);

        if ($key !== null) {
            return NotificationCatalog::entry($key);
        }

        if (NotificationCatalog::snapshot()['entries'] === []) {
            return null;
        }

        return NotificationCatalog::discover($origin, $channel, $audience);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function blockedReason(OutboundChannel $channel, array $entry, bool $critical): ?string
    {
        if (! filled($entry['system_key'] ?? null)) {
            if (! $entry['is_active']) {
                return 'Tipo pausado en el Centro de notificaciones.';
            }

            if ($channel === OutboundChannel::WhatsApp && ! $entry['whatsapp_enabled']) {
                return 'WhatsApp apagado para este tipo en el Centro de notificaciones.';
            }

            if ($channel === OutboundChannel::Mail && ! $entry['mail_enabled']) {
                return 'Correo apagado para este tipo en el Centro de notificaciones.';
            }
        }

        if (! $critical && ! NotificationCatalog::channelEnabled($channel)) {
            return $channel->label().' apagado en todo el sistema desde el Centro de notificaciones.';
        }

        return null;
    }

    /**
     * @return array{0: NotificationAudience, 1: string|null}
     */
    private static function audienceFor(OutboundChannel $channel, string $recipient): array
    {
        if ($channel === OutboundChannel::Telegram) {
            return [NotificationAudience::Internal, null];
        }

        if ($channel === OutboundChannel::Mail) {
            return [InternalDirectory::isInternalEmail($recipient) ? NotificationAudience::Internal : NotificationAudience::External, null];
        }

        $name = InternalDirectory::internalNameForPhone($recipient);

        return [$name !== null ? NotificationAudience::Internal : NotificationAudience::External, $name];
    }

    private static function recipientKey(OutboundChannel $channel, string $recipient): ?string
    {
        if ($recipient === '') {
            return null;
        }

        if ($channel === OutboundChannel::WhatsApp) {
            $key = InternalContactResolver::phoneKey($recipient);

            return $key !== '' ? $key : null;
        }

        return mb_substr(mb_strtolower($recipient), 0, 64);
    }
}
