<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Models\WhatsAppInboundMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un aviso del webhook de UltraMsg.
 *
 * Forma del aviso (event_type «message_received»):
 * `{"event_type":"message_received","instanceId":"…","data":{"id":"false_584121234567@c.us_3EB0…",
 * "from":"584121234567@c.us","type":"chat","body":"Si","fromMe":false,…}}`
 *
 * - «message_ack» (los checks de un mensaje enviado) no se guarda como mensaje recibido:
 *   actualiza la entrega del WhatsApp al paciente, si es uno de ellos.
 * - Cada mensaje se guarda una sola vez (id único): UltraMsg reintenta lo que no recibe 200.
 * - Se descartan los mensajes propios, de grupos y los avisos que no son de mensaje recibido.
 * - Solo los números con una pregunta pendiente se tratan como respuesta; el resto se ignora
 *   (ese número de WhatsApp recibe de todo).
 */
final class UltraMsgInboundHandler
{
    public const EVENT_MESSAGE_RECEIVED = 'message_received';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function handle(array $payload): ?WhatsAppInboundMessage
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $messageId = is_scalar($data['id'] ?? null) ? trim((string) $data['id']) : '';
        $eventType = is_scalar($payload['event_type'] ?? null) ? (string) $payload['event_type'] : '';

        if ($eventType === ServiceOrderPatientDeliveryTracker::EVENT_MESSAGE_ACK) {
            ServiceOrderPatientDeliveryTracker::handleWebhook($payload);

            return null;
        }

        if ($messageId === '' || $eventType !== self::EVENT_MESSAGE_RECEIVED) {
            return null;
        }

        $from = is_scalar($data['from'] ?? null) ? (string) $data['from'] : '';
        $type = is_scalar($data['type'] ?? null) ? (string) $data['type'] : '';
        $body = is_scalar($data['body'] ?? null) ? (string) $data['body'] : null;
        $isPrivateChat = str_ends_with($from, '@c.us');
        $phone = $isPrivateChat ? ServiceOrderPatientNotifier::normalizePhone(strstr($from, '@', true) ?: null) : null;

        try {
            $inbound = WhatsAppInboundMessage::query()->create([
                'provider' => 'ULTRAMSG',
                'message_id' => mb_substr($messageId, 0, 191),
                'event_type' => $eventType,
                'phone' => $phone,
                'message_type' => mb_substr($type, 0, 20),
                'body' => $body !== null ? mb_substr($body, 0, 4000) : null,
                'payload' => $payload,
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if (self::isTruthy($data['fromMe'] ?? false) || ! $isPrivateChat || $phone === null) {
            $inbound->forceFill(['result' => WhatsAppInboundMessage::RESULT_SKIPPED, 'processed_at' => now()])->save();

            return $inbound;
        }

        try {
            /** Un audio, una imagen o un sticker no es SI ni NO: cuenta como respuesta inválida. */
            $text = $type === 'chat' ? $body : null;
            $result = ServiceOrderPatientNotifier::registerReply($phone, $text, $inbound);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('WhatsApp al paciente: no se pudo procesar un mensaje recibido.', ['inbound_id' => $inbound->getKey(), 'error' => $exception->getMessage()]);

            return $inbound;
        }

        $inbound->forceFill(['result' => $result, 'processed_at' => now()])->save();

        return $inbound;
    }

    private static function isTruthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || (is_string($value) && strtolower($value) === 'true');
    }
}
