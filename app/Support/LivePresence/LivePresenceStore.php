<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Punto de entrada al almacenamiento de presencia.
 *
 * Elige Redis cuando la aplicación ya usa Redis (producción) y la caché por
 * defecto en otro caso (desarrollo). Toda escritura está protegida: si Redis
 * o la caché fallan, se registra una vez y la petición del usuario sigue sin
 * enterarse. El monitor nunca puede tumbar el sistema que observa.
 */
final class LivePresenceStore
{
    private static ?LivePresenceRepository $repository = null;

    private static bool $failureLogged = false;

    public static function repository(): LivePresenceRepository
    {
        return self::$repository ??= self::build();
    }

    /**
     * Para pruebas: fuerza un repositorio o vuelve a resolverlo.
     */
    public static function swap(?LivePresenceRepository $repository): void
    {
        self::$repository = $repository;
    }

    public static function usesRedis(): bool
    {
        $mode = (string) config('live-presence.store', 'auto');

        if ($mode === 'redis') {
            return true;
        }

        if ($mode === 'cache') {
            return false;
        }

        return config('cache.default') === 'redis'
            || config('session.driver') === 'redis'
            || config('queue.default') === 'redis';
    }

    /**
     * Ejecuta una escritura sin dejar que un fallo del almacenamiento suba.
     */
    public static function safely(callable $callback): void
    {
        try {
            $callback(self::repository());
        } catch (Throwable $exception) {
            if (! self::$failureLogged) {
                self::$failureLogged = true;

                try {
                    Log::warning('LivePresence: el almacenamiento de presencia falló; se omite sin afectar la petición.', [
                        'driver' => self::usesRedis() ? 'redis' : 'cache',
                        'error' => $exception->getMessage(),
                    ]);
                } catch (Throwable) {
                }
            }
        }
    }

    private static function build(): LivePresenceRepository
    {
        $sessionTtl = max(60, (int) config('live-presence.session_ttl', 300));
        $timelineSize = max(5, (int) config('live-presence.timeline_size', 50));
        $timelineTtl = max(600, (int) config('live-presence.timeline_ttl', 86400));
        $samples = max(50, (int) config('live-presence.performance_samples', 500));

        if (self::usesRedis()) {
            return new RedisLivePresenceRepository(
                (string) config('live-presence.redis_connection', 'default'),
                $sessionTtl,
                $timelineSize,
                $timelineTtl,
                $samples,
            );
        }

        return new CacheLivePresenceRepository(
            Cache::store(config('live-presence.cache_store') ?: null),
            $sessionTtl,
            $timelineSize,
            $timelineTtl,
            $samples,
        );
    }
}
