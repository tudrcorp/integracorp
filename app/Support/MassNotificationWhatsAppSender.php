<?php

declare(strict_types=1);

namespace App\Support;

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

        if ($throttle) {
            sleep(20);
        }

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
