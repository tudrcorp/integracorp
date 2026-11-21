<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Support\MassNotificationWhatsAppSender;
use CurlHandle;
use Illuminate\Support\Facades\Log;

/**
 * Punto único por el que pasa cada llamada a UltraMsg.
 *
 * - En los envíos síncronos reemplaza a curl_exec($curl): si el Centro de
 *   notificaciones lo tiene apagado, no llama a UltraMsg y devuelve false (el
 *   llamador lo trata como un envío fallido, como ya hace con cualquier error).
 * - En los jobs se decide al empezar (decide()) para no gastar reintentos en un
 *   mensaje bloqueado, y se registra al terminar (record()).
 */
final class OutboundWhatsApp
{
    /** Momento del último bloqueo en este proceso (para no reportarlo como falla). */
    private static float $lastBlockedAt = 0.0;

    /**
     * ¿Se bloqueó un WhatsApp en este proceso hace menos de unos segundos? Lo usa el
     * aviso de «envío fallido» al administrador para no confundir un bloqueo con un error.
     */
    public static function blockedRecently(float $seconds = 5.0): bool
    {
        return self::$lastBlockedAt > 0 && (microtime(true) - self::$lastBlockedAt) <= $seconds;
    }

    /**
     * @param  array<string, mixed>  $params  campos que se envían a UltraMsg (to, body, caption, document, filename, image, video)
     * @param  array<string, mixed>  $context  catalog, notification_key, origin, recipient_name
     */
    public static function exec(CurlHandle $curl, array $params, array $context = []): string|bool
    {
        $decision = self::decide((string) ($params['to'] ?? ''), $context);

        if ($decision->blocked()) {
            self::recordBlocked($decision, $params);

            return false;
        }

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $succeeded = $error === '' && MassNotificationWhatsAppSender::apiResponseSucceeded($response, $httpCode);

        OutboundGuard::record($decision, $succeeded ? OutboundMessageStatus::Sent : OutboundMessageStatus::Failed, [
            ...self::data($params),
            'error' => $succeeded ? null : ($error !== '' ? $error : 'UltraMsg respondió '.$httpCode.': '.mb_substr(is_string($response) ? $response : 'sin respuesta', 0, 300)),
        ]);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function decide(string $phone, array $context = []): OutboundDecision
    {
        return OutboundGuard::evaluate(OutboundChannel::WhatsApp, $phone, $context);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function recordBlocked(OutboundDecision $decision, array $params): void
    {
        self::$lastBlockedAt = microtime(true);

        Log::info('Centro de notificaciones: WhatsApp bloqueado.', [
            'catalog' => $decision->catalogKey,
            'origin' => $decision->origin,
            'reason' => $decision->blockedReason,
        ]);

        OutboundGuard::record($decision, OutboundMessageStatus::Blocked, self::data($params));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function record(OutboundDecision $decision, bool $succeeded, array $params, ?string $error = null): void
    {
        OutboundGuard::record($decision, $succeeded ? OutboundMessageStatus::Sent : OutboundMessageStatus::Failed, [
            ...self::data($params),
            'error' => $succeeded ? null : $error,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{recipient: string, body: string|null, type: string, attachment: string|null}
     */
    public static function data(array $params): array
    {
        $type = match (true) {
            filled($params['document'] ?? null) => 'document',
            filled($params['video'] ?? null) => 'video',
            filled($params['image'] ?? null) => 'image',
            default => 'chat',
        };

        $body = $params['body'] ?? $params['caption'] ?? null;

        return [
            'recipient' => (string) ($params['to'] ?? ''),
            'body' => is_string($body) ? $body : null,
            'type' => $type,
            'attachment' => is_string($params['filename'] ?? null) ? $params['filename'] : null,
        ];
    }
}
