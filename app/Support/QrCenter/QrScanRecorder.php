<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

use App\Models\QrScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anota una lectura del QR o una visita a una página alojada.
 *
 * No guarda la IP ni el navegador: solo un resumen que cambia cada día (sirve para
 * contar visitantes únicos por día) y el tipo de dispositivo. Si falla, la persona
 * llega igual a su destino: las estadísticas nunca bloquean la redirección.
 */
final class QrScanRecorder
{
    public static function record(string $kind, ?int $destinationId, ?int $hostedPageId, Request $request): void
    {
        try {
            $userAgent = (string) $request->userAgent();

            QrScan::query()->create([
                'qr_destination_id' => $destinationId,
                'qr_hosted_page_id' => $hostedPageId,
                'kind' => $kind,
                'visitor_hash' => self::visitorHash((string) $request->ip(), $userAgent, now()->toDateString()),
                'device' => self::device($userAgent),
                'is_bot' => self::isBot($userAgent),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Centro de QR: no se pudo anotar la lectura', ['error' => $exception->getMessage()]);
        }
    }

    public static function visitorHash(string $ip, string $userAgent, string $day): string
    {
        return hash_hmac('sha256', $ip.'|'.$userAgent.'|'.$day, (string) config('app.key'));
    }

    public static function isBot(string $userAgent): bool
    {
        return $userAgent === ''
            || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|headless|monitor/i', $userAgent);
    }

    public static function device(string $userAgent): string
    {
        return match (true) {
            self::isBot($userAgent) => 'bot',
            (bool) preg_match('/ipad|tablet|(android(?!.*mobile))/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobile|iphone|ipod|android/i', $userAgent) => 'movil',
            default => 'escritorio',
        };
    }
}
