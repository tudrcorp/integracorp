<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Todos los correos del sistema pasan por aquí (eventos de Laravel), sin tocar
 * cada Mailable:
 *
 * - MessageSending: el guardián decide; si el tipo o el canal está apagado,
 *   devuelve false y Laravel no envía el correo. Si puede salir, queda en la
 *   bitácora como «Sin confirmar».
 * - MessageSent: el servidor de correo lo aceptó → «Enviado».
 *
 * El tipo se reconoce por la clase del Mailable (o de la notificación de Laravel).
 */
final class OutboundMailListener
{
    private const HEADER = 'X-IC-Outbound-Id';

    public function onSending(MessageSending $event): ?bool
    {
        try {
            $message = $event->message;
            $data = $event->data;
            $class = $data['__laravel_mailable'] ?? $data['__laravel_notification'] ?? null;
            $origin = is_string($class) && $class !== '' ? 'mail:'.$class : 'mail:desconocido';

            $addresses = [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()];
            $first = $message->getTo()[0] ?? $addresses[0] ?? null;
            $recipient = $first instanceof Address ? $first->getAddress() : '';

            $context = ['origin' => $origin];

            if (is_string($data['notificationKey'] ?? null) && $data['notificationKey'] !== '') {
                $context['notification_key'] = $data['notificationKey'];
            }

            if ($first instanceof Address && $first->getName() !== '') {
                $context['recipient_name'] = $first->getName();
            }

            $decision = OutboundGuard::evaluate(OutboundChannel::Mail, $recipient, $context);
            $decision = self::withAudience($decision, $addresses);

            $payload = [
                'recipient' => $recipient,
                'recipients_count' => count($addresses),
                'subject' => $message->getSubject(),
                'body' => self::plainBody($message),
                'type' => 'mail',
                'attachment' => self::attachmentNames($message),
            ];

            if ($decision->blocked()) {
                OutboundGuard::record($decision, OutboundMessageStatus::Blocked, $payload);

                return false;
            }

            $id = OutboundGuard::record($decision, OutboundMessageStatus::Pending, $payload);

            if ($id !== null) {
                $message->getHeaders()->addTextHeader(self::HEADER, (string) $id);
            }
        } catch (Throwable) {
            /** Un error del Centro nunca detiene un correo. */
        }

        return null;
    }

    public function onSent(MessageSent $event): void
    {
        try {
            $original = $event->sent->getOriginalMessage();

            if (! $original instanceof Email) {
                return;
            }

            $header = $original->getHeaders()->get(self::HEADER);

            if ($header === null) {
                return;
            }

            OutboundGuard::markStatus((int) $header->getBodyAsString(), OutboundMessageStatus::Sent);
        } catch (Throwable) {
        }
    }

    /**
     * Interno solo si TODOS los destinatarios son del personal (@tudrencasa.com).
     *
     * @param  list<Address>  $addresses
     */
    private static function withAudience(OutboundDecision $decision, array $addresses): OutboundDecision
    {
        $internal = $addresses !== [] && collect($addresses)->every(fn (Address $address): bool => InternalDirectory::isInternalEmail($address->getAddress()));
        $audience = $internal ? NotificationAudience::Internal : NotificationAudience::External;

        if ($audience === $decision->audience) {
            return $decision;
        }

        return new OutboundDecision($decision->channel, $decision->catalogKey, $audience, $decision->origin, $decision->blockedReason, $decision->critical, $decision->bulk, $decision->recipientName);
    }

    private static function plainBody(Email $message): ?string
    {
        $text = $message->getTextBody();

        if (! is_string($text) || trim($text) === '') {
            $html = $message->getHtmlBody();

            if (! is_string($html) || $html === '') {
                return null;
            }

            $html = (string) preg_replace('#<(style|script|head)[^>]*>.*?</\1>#si', ' ', $html);
            $text = html_entity_decode(strip_tags((string) preg_replace('#<(br|/p|/div|/tr|/h[1-6])[^>]*>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $text = trim((string) preg_replace("/[ \t]+/", ' ', (string) preg_replace("/\n\s*\n+/", "\n\n", $text)));

        return $text === '' ? null : mb_substr($text, 0, 4000);
    }

    private static function attachmentNames(Email $message): ?string
    {
        $names = [];

        foreach ($message->getAttachments() as $attachment) {
            $names[] = (string) ($attachment->getFilename() ?? 'adjunto');
        }

        return $names === [] ? null : mb_substr(implode(', ', $names), 0, 255);
    }
}
