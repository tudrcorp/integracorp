<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Support\MassNotificationWhatsAppSender;

/**
 * Llamadas a UltraMsg que devuelven el resultado completo (éxito, id del mensaje y error),
 * no solo un booleano como los envíos históricos de NotificationController.
 *
 * No decide si el mensaje puede salir ni lo registra: eso lo hace quien llama con
 * {@see \App\Support\NotificationCenter\OutboundWhatsApp::decide()} y ::record().
 */
final class UltraMsgClient
{
    /**
     * @return array{ok: bool, id: string|null, error: string|null}
     */
    public static function sendDocument(string $phone, string $caption, string $documentUrl, string $filename, ?string $referenceId = null): array
    {
        return self::post((string) config('parameters.CURLOPT_URL_DOCUMENT'), [
            'to' => $phone,
            'filename' => $filename,
            'document' => $documentUrl,
            'caption' => $caption,
            ...($referenceId !== null && $referenceId !== '' ? ['referenceId' => $referenceId] : []),
        ]);
    }

    /**
     * Raíz de la instancia (https://api.ultramsg.com/instanceNNN), tomada de la URL de envío
     * ya configurada o, si no, de ULTRAMSG_INSTANCE_ID. Null si no hay cómo saberla.
     */
    public static function instanceBaseUrl(): ?string
    {
        foreach (['parameters.CURLOPT_URL', 'parameters.CURLOPT_URL_DOCUMENT'] as $key) {
            if (preg_match('#^(https?://[^/\s]+/instance\d+)(/|$)#i', (string) config($key, ''), $matches) === 1) {
                return $matches[1];
            }
        }

        $digits = preg_replace('/\D+/', '', (string) config('services.ultramsg_webhook.instance_id', ''));

        return $digits !== '' && $digits !== null ? 'https://api.ultramsg.com/instance'.$digits : null;
    }

    public static function configured(): bool
    {
        return filled(config('parameters.TOKEN')) && self::instanceBaseUrl() !== null;
    }

    /**
     * Consulta de solo lectura a la instancia (`/instance/status`, `/messages`). Tiempo corto:
     * la llama una pantalla o la revisión periódica, nunca debe colgarlas.
     *
     * @param  array<string, scalar>  $query
     * @return array{response: string|false, http_code: int, curl_error: string}
     */
    public static function get(string $path, array $query = [], int $timeoutSeconds = 5): array
    {
        $base = self::instanceBaseUrl();
        $token = (string) config('parameters.TOKEN');

        if ($base === null || $token === '') {
            return ['response' => false, 'http_code' => 0, 'curl_error' => 'UltraMsg no está configurado en este servidor.'];
        }

        $curl = curl_init();

        try {
            curl_setopt_array($curl, [
                CURLOPT_URL => $base.'/'.ltrim($path, '/').'?'.http_build_query(['token' => $token, ...$query]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => min(3, $timeoutSeconds),
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ]);

            $response = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
        } finally {
            curl_close($curl);
        }

        return ['response' => is_string($response) ? $response : false, 'http_code' => $httpCode, 'curl_error' => $curlError];
    }

    /**
     * Check actual de un mensaje enviado por la API (pending, server, device, read, played),
     * buscado por el id que UltraMsg devolvió al enviarlo. Null si no se pudo saber.
     */
    public static function messageAck(string $ultramsgMessageId): ?string
    {
        if (trim($ultramsgMessageId) === '') {
            return null;
        }

        $result = self::get('messages', ['page' => 1, 'limit' => 1, 'status' => 'all', 'sort' => 'desc', 'id' => $ultramsgMessageId]);

        return self::interpretMessageAck($result['response'], $result['http_code'], $ultramsgMessageId);
    }

    public static function interpretMessageAck(mixed $response, int $httpCode, string $ultramsgMessageId): ?string
    {
        if ($httpCode < 200 || $httpCode >= 300 || ! is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        $messages = is_array($decoded) ? ($decoded['messages'] ?? (array_is_list($decoded) ? $decoded : [])) : [];

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (! is_array($message) || (string) ($message['id'] ?? '') !== $ultramsgMessageId) {
                continue;
            }

            $ack = $message['ack'] ?? null;

            return is_scalar($ack) && (string) $ack !== '' ? (string) $ack : null;
        }

        return null;
    }

    /**
     * @return array{ok: bool, id: string|null, error: string|null}
     */
    public static function sendText(string $phone, string $body): array
    {
        return self::post((string) config('parameters.CURLOPT_URL'), [
            'to' => $phone,
            'body' => $body,
        ]);
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{ok: bool, id: string|null, error: string|null}
     */
    private static function post(string $url, array $fields): array
    {
        $token = (string) config('parameters.TOKEN');

        if ($url === '' || $token === '') {
            return ['ok' => false, 'id' => null, 'error' => 'UltraMsg no está configurado en este servidor (config/parameters.php).'];
        }

        $curl = curl_init();

        try {
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query(['token' => $token, ...$fields]),
                CURLOPT_HTTPHEADER => ['content-type: application/x-www-form-urlencoded'],
            ]);

            $response = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
        } finally {
            curl_close($curl);
        }

        return self::interpret($response, $httpCode, $curlError);
    }

    /**
     * @return array{ok: bool, id: string|null, error: string|null}
     */
    public static function interpret(mixed $response, int $httpCode, string $curlError = ''): array
    {
        if ($curlError !== '') {
            return ['ok' => false, 'id' => null, 'error' => 'Sin conexión con UltraMsg: '.$curlError];
        }

        if (! MassNotificationWhatsAppSender::apiResponseSucceeded($response, $httpCode)) {
            return ['ok' => false, 'id' => null, 'error' => 'UltraMsg respondió '.$httpCode.': '.mb_substr(is_string($response) ? $response : 'sin respuesta', 0, 300)];
        }

        $decoded = json_decode((string) $response, true);
        $id = is_array($decoded) && isset($decoded['id']) && is_scalar($decoded['id']) ? (string) $decoded['id'] : null;

        return ['ok' => true, 'id' => $id, 'error' => null];
    }
}
