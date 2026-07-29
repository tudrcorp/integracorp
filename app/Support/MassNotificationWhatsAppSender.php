<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class MassNotificationWhatsAppSender
{
    /**
     * @param  array<string, mixed>  $dataNotificationArray
     * @param  array<string, mixed>  $infoNotificationArray
     */
    public static function send(array $dataNotificationArray, array $infoNotificationArray, bool $throttle = true): MassNotificationWhatsAppSendResult
    {
        $phone = trim((string) ($dataNotificationArray['phone'] ?? ''));

        if ($phone === '') {
            return MassNotificationWhatsAppSendResult::fail('Teléfono vacío o no disponible');
        }

        // Normaliza el teléfono ya trimmeado para el resto del flujo.
        $dataNotificationArray['phone'] = $phone;

        if (! $throttle) {
            return self::sendToApi($dataNotificationArray, $infoNotificationArray);
        }

        $lockKey = (string) config('mass-notifications.whatsapp_lock_key', 'mass-notification-whatsapp-send');
        $lockSeconds = max(30, (int) config('mass-notifications.whatsapp_lock_seconds', 90));
        $waitSeconds = max(0, (int) config('mass-notifications.whatsapp_lock_wait_seconds', 0));

        $lock = Cache::lock($lockKey, $lockSeconds);

        try {
            return $lock->block($waitSeconds, function () use ($dataNotificationArray, $infoNotificationArray): MassNotificationWhatsAppSendResult {
                self::paceBeforeSend();

                $result = self::sendToApi($dataNotificationArray, $infoNotificationArray);

                if ($result->success) {
                    self::rememberLastSentAt();
                }

                return $result;
            });
        } catch (LockTimeoutException $exception) {
            Log::debug('MassNotificationWhatsAppSender: canal ocupado, reintentar', [
                'phone' => $phone,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Espera el intervalo configurado desde el último envío exitoso.
     * Debe llamarse solo con el lock del canal adquirido.
     */
    public static function paceBeforeSend(): void
    {
        $throttleSeconds = max(0, (int) config('mass-notifications.whatsapp_throttle_seconds', 20));

        if ($throttleSeconds === 0) {
            return;
        }

        $lastSentAt = (int) Cache::get(self::lastSentCacheKey(), 0);

        if ($lastSentAt <= 0) {
            return;
        }

        $elapsed = time() - $lastSentAt;
        $wait = $throttleSeconds - $elapsed;

        if ($wait > 0) {
            sleep($wait);
        }
    }

    public static function rememberLastSentAt(): void
    {
        $throttleSeconds = max(1, (int) config('mass-notifications.whatsapp_throttle_seconds', 20));

        Cache::put(self::lastSentCacheKey(), time(), $throttleSeconds * 10);
    }

    public static function lastSentCacheKey(): string
    {
        return (string) config(
            'mass-notifications.whatsapp_last_sent_cache_key',
            'mass-notification-whatsapp-last-sent-at',
        );
    }

    /**
     * @param  array<string, mixed>  $dataNotificationArray
     * @param  array<string, mixed>  $infoNotificationArray
     */
    private static function sendToApi(array $dataNotificationArray, array $infoNotificationArray): MassNotificationWhatsAppSendResult
    {
        $phone = trim((string) ($dataNotificationArray['phone'] ?? ''));

        if ($phone === '') {
            return MassNotificationWhatsAppSendResult::fail('Teléfono vacío o no disponible');
        }

        $headerTitle = $infoNotificationArray['header_title'] ?? null;
        $fullName = (string) ($dataNotificationArray['fullName'] ?? '');
        $header = filled($headerTitle) ? trim((string) $headerTitle.' '.$fullName) : '';
        $content = (string) ($infoNotificationArray['content'] ?? '');

        $body = <<<HTML

        {$header} 

        {$content}

        HTML;

        $type = (string) ($infoNotificationArray['type'] ?? 'url');
        $file = $infoNotificationArray['file'] ?? null;

        $params = match ($type) {
            'image' => [
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'image' => rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.ltrim((string) $file, '/'),
                'caption' => $body,
            ],
            'video' => [
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'video' => rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.ltrim((string) $file, '/'),
                'caption' => $body,
            ],
            default => [
                'token' => config('parameters.TOKEN'),
                'to' => $phone,
                'body' => $body,
            ],
        };

        $endpoint = match ($type) {
            'image' => (string) config('parameters.CURLOPT_URL_IMAGE'),
            'video' => (string) config('parameters.CURLOPT_URL_VIDEO'),
            default => (string) config('parameters.CURLOPT_URL'),
        };

        if ($endpoint === '') {
            return MassNotificationWhatsAppSendResult::fail(
                'Endpoint de WhatsApp no configurado para el tipo: '.$type,
                $phone,
            );
        }

        if (in_array($type, ['image', 'video'], true) && blank($file)) {
            return MassNotificationWhatsAppSendResult::fail(
                'Archivo multimedia vacío para el tipo: '.$type,
                $phone,
            );
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'content-type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (filled($err)) {
            Log::error('MassNotificationWhatsAppSender: error cURL', [
                'phone' => $phone,
                'error' => $err,
            ]);

            return MassNotificationWhatsAppSendResult::fail(
                'Error de conexión cURL: '.$err,
                $phone,
            );
        }

        if (! self::apiResponseSucceeded($response, $httpCode)) {
            $message = self::extractApiErrorMessage($response, $httpCode);

            Log::warning('MassNotificationWhatsAppSender: API rechazó el envío', [
                'phone' => $phone,
                'http_code' => $httpCode,
                'response' => $response,
            ]);

            return MassNotificationWhatsAppSendResult::fail($message, $phone);
        }

        Log::info('MassNotificationWhatsAppSender: enviado', [
            'phone' => $phone,
        ]);

        return MassNotificationWhatsAppSendResult::ok($phone);
    }

    public static function apiResponseSucceeded(mixed $response, int $httpCode = 200): bool
    {
        if ($httpCode < 200 || $httpCode >= 300) {
            return false;
        }

        if (! is_string($response) || trim($response) === '') {
            return false;
        }

        $decoded = json_decode($response, true);

        if (! is_array($decoded)) {
            return false;
        }

        if (array_key_exists('error', $decoded) && filled($decoded['error'])) {
            return false;
        }

        if (($decoded['sent'] ?? null) === 'true' || ($decoded['sent'] ?? null) === true) {
            return true;
        }

        return isset($decoded['id']) || isset($decoded['message']);
    }

    private static function extractApiErrorMessage(mixed $response, int $httpCode): string
    {
        if (! is_string($response) || trim($response) === '') {
            return sprintf('La API de WhatsApp respondió con HTTP %d sin detalle', $httpCode);
        }

        $decoded = json_decode($response, true);

        if (is_array($decoded)) {
            $error = $decoded['error'] ?? $decoded['message'] ?? null;

            if (is_string($error) && filled($error)) {
                return mb_substr($error, 0, 1000);
            }

            if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
                return mb_substr($error['message'], 0, 1000);
            }
        }

        return mb_substr(sprintf('La API de WhatsApp rechazó el envío (HTTP %d): %s', $httpCode, $response), 0, 1000);
    }
}
