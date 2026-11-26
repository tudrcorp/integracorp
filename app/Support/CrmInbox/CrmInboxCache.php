<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Caché propia del CRM (Redis en producción). Si Redis no responde, el CRM sigue funcionando
 * contra la base: cada lectura o escritura aquí es una optimización, nunca una condición.
 */
final class CrmInboxCache
{
    /**
     * Tras un fallo de conexión no se vuelve a intentar en el mismo proceso por unos segundos,
     * para que una caída de Redis no sume un tiempo de espera a cada sondeo.
     */
    private static ?float $downUntil = null;

    public static function store(): Repository
    {
        return Cache::store(CrmHandoffIngress::resolveCacheStore(
            config('crm-inbox.cache_store'),
            app()->runningUnitTests() || app()->environment('local'),
        ));
    }

    /**
     * @template T
     *
     * @param  callable(Repository): T  $callback
     * @param  T  $fallback
     * @return T
     */
    public static function attempt(callable $callback, mixed $fallback = null): mixed
    {
        if (self::$downUntil !== null && microtime(true) < self::$downUntil) {
            return $fallback;
        }

        try {
            $result = $callback(self::store());
            self::$downUntil = null;

            return $result;
        } catch (Throwable) {
            self::$downUntil = microtime(true) + 15;

            return $fallback;
        }
    }

    public static function reset(): void
    {
        self::$downUntil = null;
    }
}
