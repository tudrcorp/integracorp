<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * Presencia sobre la caché de Laravel, para desarrollo (caché en base de datos).
 *
 * Mismo contrato que la versión Redis. El índice de conectados es un arreglo
 * en una sola llave, protegido con un lock cuando el store lo soporta.
 */
final class CacheLivePresenceRepository implements LivePresenceRepository
{
    private const SESSION_PREFIX = 'lp:s:';

    private const INDEX = 'lp:online';

    private const TIMELINE_PREFIX = 'lp:t:';

    private const PERF = 'lp:perf';

    public function __construct(
        private readonly Repository $cache,
        private readonly int $sessionTtl,
        private readonly int $timelineSize,
        private readonly int $timelineTtl,
        private readonly int $performanceSamples,
    ) {}

    public function touch(string $sessionKey, array $fields, bool $countRequest): void
    {
        $now = time();
        $key = self::SESSION_PREFIX.$sessionKey;
        $current = $this->cache->get($key);
        $current = is_array($current) ? $current : [];

        foreach ($fields as $name => $value) {
            if ($value !== null) {
                $current[$name] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
        }

        $current['first_seen'] ??= (string) $now;
        $current['last_seen'] = (string) $now;

        if ($countRequest) {
            $current['requests'] = (string) (((int) ($current['requests'] ?? 0)) + 1);
        }

        $this->cache->put($key, $current, $this->sessionTtl);

        $this->withLock(self::INDEX, function () use ($sessionKey, $now): void {
            $index = $this->cache->get(self::INDEX);
            $index = is_array($index) ? $index : [];
            $index[$sessionKey] = $now;
            $cutoff = $now - $this->sessionTtl;
            $index = array_filter($index, static fn (mixed $seen): bool => (int) $seen >= $cutoff);
            $this->cache->put(self::INDEX, $index, $this->sessionTtl);
        });
    }

    public function online(int $windowSeconds): array
    {
        $index = $this->cache->get(self::INDEX);

        if (! is_array($index) || $index === []) {
            return [];
        }

        $cutoff = time() - $windowSeconds;
        $index = array_filter($index, static fn (mixed $seen): bool => (int) $seen >= $cutoff);
        arsort($index);

        $sessions = [];

        foreach (array_keys($index) as $sessionKey) {
            $row = $this->cache->get(self::SESSION_PREFIX.$sessionKey);

            if (is_array($row) && $row !== []) {
                $sessions[] = ['session_key' => (string) $sessionKey, ...array_map('strval', $row)];
            }
        }

        return $sessions;
    }

    public function pushTimeline(int $userId, array $event): void
    {
        $key = self::TIMELINE_PREFIX.$userId;

        $this->withLock($key, function () use ($key, $event): void {
            $items = $this->cache->get($key);
            $items = is_array($items) ? $items : [];
            array_unshift($items, $event);
            $this->cache->put($key, array_slice($items, 0, $this->timelineSize), $this->timelineTtl);
        });
    }

    public function timeline(int $userId, int $limit): array
    {
        $items = $this->cache->get(self::TIMELINE_PREFIX.$userId);

        return array_values(array_filter(array_slice(is_array($items) ? $items : [], 0, $limit), 'is_array'));
    }

    public function recordRequestDuration(float $milliseconds): void
    {
        $this->withLock(self::PERF, function () use ($milliseconds): void {
            $samples = $this->cache->get(self::PERF);
            $samples = is_array($samples) ? $samples : [];
            array_unshift($samples, [time(), round($milliseconds, 1)]);
            $this->cache->put(self::PERF, array_slice($samples, 0, $this->performanceSamples), 3600);
        });
    }

    public function durationSamples(): array
    {
        $samples = $this->cache->get(self::PERF);

        return array_values(array_map(
            static fn (array $sample): array => [(int) ($sample[0] ?? 0), (float) ($sample[1] ?? 0)],
            array_filter(is_array($samples) ? $samples : [], 'is_array'),
        ));
    }

    public function driverName(): string
    {
        return 'cache';
    }

    /**
     * Lectura-modificación-escritura protegida si el store tiene locks; si no,
     * se hace igual: en desarrollo perder un latido no importa.
     */
    private function withLock(string $name, callable $callback): void
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            $callback();

            return;
        }

        try {
            $store->lock($name.':lock', 3)->block(1, $callback);
        } catch (Throwable) {
            $callback();
        }
    }
}
