<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Salud del bot: responde (getMe) y el webhook no acumula errores ni mensajes
 * pendientes (getWebhookInfo). El resultado se guarda unos segundos en caché
 * para que el vigilante y la pantalla no golpeen a Telegram en cada carga.
 */
final class TelegramHealth
{
    private const CACHE_KEY = 'telegram:health';

    /** Mensajes pendientes en el webhook a partir de los cuales algo anda mal. */
    public const PENDING_UPDATES_THRESHOLD = 20;

    /**
     * @return array{
     *     enabled: bool,
     *     configured: bool,
     *     ok: bool,
     *     bot_username: ?string,
     *     webhook_url: ?string,
     *     pending_update_count: int,
     *     webhook_last_error: ?string,
     *     webhook_last_error_at: ?int,
     *     error: ?string,
     *     error_kind: ?string,
     *     checked_at: int
     * }
     */
    public static function check(bool $fresh = false): array
    {
        if (! $fresh) {
            $cached = Cache::get(self::CACHE_KEY);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = self::probe(TelegramBotClient::make());

        try {
            Cache::put(self::CACHE_KEY, $result, max(10, (int) config('telegram.health_cache_seconds', 90)));
        } catch (Throwable) {
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function probe(TelegramBotClient $client): array
    {
        $base = [
            'enabled' => (bool) config('telegram.enabled', true),
            'configured' => $client->isConfigured(),
            'ok' => false,
            'bot_username' => null,
            'webhook_url' => null,
            'pending_update_count' => 0,
            'webhook_last_error' => null,
            'webhook_last_error_at' => null,
            'error' => null,
            'error_kind' => null,
            'checked_at' => time(),
        ];

        if (! $base['enabled'] || ! $base['configured']) {
            return $base;
        }

        try {
            $me = $client->getMe();
            $webhook = $client->getWebhookInfo();
        } catch (TelegramApiException $exception) {
            return [...$base, 'error' => $exception->getMessage(), 'error_kind' => $exception->kind];
        }

        $lastErrorAt = isset($webhook['last_error_date']) ? (int) $webhook['last_error_date'] : null;

        return [
            ...$base,
            'ok' => true,
            'bot_username' => isset($me['username']) ? (string) $me['username'] : null,
            'webhook_url' => filled($webhook['url'] ?? null) ? (string) $webhook['url'] : null,
            'pending_update_count' => (int) ($webhook['pending_update_count'] ?? 0),
            'webhook_last_error' => filled($webhook['last_error_message'] ?? null) ? (string) $webhook['last_error_message'] : null,
            'webhook_last_error_at' => $lastErrorAt,
        ];
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
