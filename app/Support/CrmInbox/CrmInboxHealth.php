<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use Illuminate\Contracts\Cache\Repository;

/**
 * Salud del envío por WhatsApp (n8n), compartida por todos los analistas. Tres fallos seguidos
 * encienden el aviso general; el primer envío que sale lo apaga.
 */
final class CrmInboxHealth
{
    public const THRESHOLD = 3;

    private const FAILURES = 'crm-inbox:wa-failures';

    private const SINCE = 'crm-inbox:wa-down-since';

    public static function delivered(): void
    {
        CrmInboxCache::attempt(static function (Repository $store): void {
            if ($store->get(self::FAILURES) !== null) {
                $store->forget(self::FAILURES);
                $store->forget(self::SINCE);
                CrmInboxPulse::bump();
            }
        });
    }

    public static function failed(): void
    {
        CrmInboxCache::attempt(static function (Repository $store): void {
            $store->add(self::FAILURES, 0, now()->addMinutes(30));
            $failures = (int) $store->increment(self::FAILURES);

            if ($failures === self::THRESHOLD) {
                $store->put(self::SINCE, now()->getTimestamp(), now()->addMinutes(30));
                CrmInboxPulse::bump();
            }
        });
    }

    /**
     * @return array{down: bool, since: ?int}
     */
    public static function status(): array
    {
        return CrmInboxCache::attempt(static function (Repository $store): array {
            $failures = (int) $store->get(self::FAILURES, 0);
            $since = $store->get(self::SINCE);

            return [
                'down' => $failures >= self::THRESHOLD,
                'since' => is_numeric($since) ? (int) $since : null,
            ];
        }, ['down' => false, 'since' => null]);
    }
}
