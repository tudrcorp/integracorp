<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Presencia en Redis (producción).
 *
 * - `lp:s:{sesión}`: hash con los campos de la sesión, vence si deja de latir.
 * - `lp:online`: conjunto ordenado sesión → último latido; listar conectados es
 *   un rango por puntaje más un HGETALL por sesión, todo en una sola tubería.
 * - `lp:t:{usuario}`: lista acotada con la línea de tiempo del usuario.
 * - `lp:perf`: lista acotada con las últimas duraciones de petición.
 */
final class RedisLivePresenceRepository implements LivePresenceRepository
{
    private const SESSION_PREFIX = 'lp:s:';

    private const INDEX = 'lp:online';

    private const TIMELINE_PREFIX = 'lp:t:';

    private const PERF = 'lp:perf';

    public function __construct(
        private readonly string $connectionName,
        private readonly int $sessionTtl,
        private readonly int $timelineSize,
        private readonly int $timelineTtl,
        private readonly int $performanceSamples,
    ) {}

    public function touch(string $sessionKey, array $fields, bool $countRequest): void
    {
        $now = time();
        $key = self::SESSION_PREFIX.$sessionKey;
        $values = self::stringify([...$fields, 'last_seen' => $now]);

        $this->redis()->pipeline(function ($pipe) use ($key, $values, $countRequest, $sessionKey, $now): void {
            $pipe->hmset($key, $values);
            $pipe->hsetnx($key, 'first_seen', (string) $now);

            if ($countRequest) {
                $pipe->hincrby($key, 'requests', 1);
            }

            $pipe->expire($key, $this->sessionTtl);
            $pipe->zadd(self::INDEX, $now, $sessionKey);
            $pipe->zremrangebyscore(self::INDEX, '-inf', (string) ($now - $this->sessionTtl));
        });
    }

    public function online(int $windowSeconds): array
    {
        $keys = $this->redis()->zrevrangebyscore(self::INDEX, '+inf', (string) (time() - $windowSeconds));

        if (! is_array($keys) || $keys === []) {
            return [];
        }

        $rows = $this->redis()->pipeline(function ($pipe) use ($keys): void {
            foreach ($keys as $sessionKey) {
                $pipe->hgetall(self::SESSION_PREFIX.$sessionKey);
            }
        });

        $sessions = [];

        foreach (array_values($keys) as $index => $sessionKey) {
            $row = $rows[$index] ?? null;

            if (is_array($row) && $row !== []) {
                $sessions[] = ['session_key' => (string) $sessionKey, ...array_map('strval', $row)];
            }
        }

        return $sessions;
    }

    public function pushTimeline(int $userId, array $event): void
    {
        $key = self::TIMELINE_PREFIX.$userId;
        $payload = (string) json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->redis()->pipeline(function ($pipe) use ($key, $payload): void {
            $pipe->lpush($key, $payload);
            $pipe->ltrim($key, 0, $this->timelineSize - 1);
            $pipe->expire($key, $this->timelineTtl);
        });
    }

    public function timeline(int $userId, int $limit): array
    {
        $items = $this->redis()->lrange(self::TIMELINE_PREFIX.$userId, 0, max(0, $limit - 1));

        return array_values(array_filter(array_map(
            static fn (mixed $item): mixed => is_string($item) ? json_decode($item, true) : null,
            is_array($items) ? $items : [],
        ), 'is_array'));
    }

    public function recordRequestDuration(float $milliseconds): void
    {
        $sample = time().':'.round($milliseconds, 1);

        $this->redis()->pipeline(function ($pipe) use ($sample): void {
            $pipe->lpush(self::PERF, $sample);
            $pipe->ltrim(self::PERF, 0, $this->performanceSamples - 1);
            $pipe->expire(self::PERF, 3600);
        });
    }

    public function durationSamples(): array
    {
        $items = $this->redis()->lrange(self::PERF, 0, $this->performanceSamples - 1);
        $samples = [];

        foreach (is_array($items) ? $items : [] as $item) {
            [$timestamp, $ms] = array_pad(explode(':', (string) $item, 2), 2, '0');
            $samples[] = [(int) $timestamp, (float) $ms];
        }

        return $samples;
    }

    public function driverName(): string
    {
        return 'redis';
    }

    /**
     * Datos del servidor Redis para el panel de rendimiento.
     *
     * @return array{memory: string|null, clients: int|null, ops_per_sec: int|null}
     */
    public function serverInfo(): array
    {
        $info = $this->redis()->info();
        $flat = [];

        foreach (is_array($info) ? $info : [] as $key => $value) {
            if (is_array($value)) {
                $flat = [...$flat, ...$value];
            } else {
                $flat[$key] = $value;
            }
        }

        return [
            'memory' => isset($flat['used_memory_human']) ? (string) $flat['used_memory_human'] : null,
            'clients' => isset($flat['connected_clients']) ? (int) $flat['connected_clients'] : null,
            'ops_per_sec' => isset($flat['instantaneous_ops_per_sec']) ? (int) $flat['instantaneous_ops_per_sec'] : null,
        ];
    }

    public function increment(string $key, int $ttl): int
    {
        $value = (int) $this->redis()->incr($key);

        if ($value === 1) {
            $this->redis()->expire($key, $ttl);
        }

        return $value;
    }

    public function counters(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $values = $this->redis()->mget($keys);
        $counters = [];

        foreach (array_values($keys) as $index => $key) {
            $counters[$key] = (int) ($values[$index] ?? 0);
        }

        return $counters;
    }

    public function addToSet(string $key, string $member, int $ttl): int
    {
        $results = $this->redis()->pipeline(function ($pipe) use ($key, $member, $ttl): void {
            $pipe->sadd($key, $member);
            $pipe->expire($key, $ttl);
            $pipe->scard($key);
        });

        return (int) ($results[2] ?? 0);
    }

    public function pushList(string $key, array $item, int $size, int $ttl): void
    {
        $payload = (string) json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->redis()->pipeline(function ($pipe) use ($key, $payload, $size, $ttl): void {
            $pipe->lpush($key, $payload);
            $pipe->ltrim($key, 0, $size - 1);
            $pipe->expire($key, $ttl);
        });
    }

    public function readList(string $key, int $limit): array
    {
        $items = $this->redis()->lrange($key, 0, max(0, $limit - 1));

        return array_values(array_filter(array_map(
            static fn (mixed $item): mixed => is_string($item) ? json_decode($item, true) : null,
            is_array($items) ? $items : [],
        ), 'is_array'));
    }

    public function scoreMember(string $key, string $member, float $score, int $ttl): void
    {
        $this->redis()->pipeline(function ($pipe) use ($key, $member, $score, $ttl): void {
            $pipe->zincrby($key, $score, $member);
            $pipe->expire($key, $ttl);
        });
    }

    public function topMembers(string $key, int $limit): array
    {
        $connection = $this->redis();
        $client = $connection->client();

        /** phpredis y predis piden WITHSCORES de forma distinta. */
        $rows = $client instanceof \Redis
            ? $client->zRevRange($key, 0, max(0, $limit - 1), true)
            : $connection->command('zrevrange', [$key, 0, max(0, $limit - 1), ['withscores' => true]]);

        $members = [];

        foreach (is_array($rows) ? $rows : [] as $member => $score) {
            $members[(string) $member] = (float) $score;
        }

        return $members;
    }

    public function putValue(string $key, array $value, int $ttl): void
    {
        $this->redis()->setex($key, $ttl, (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function getValue(string $key): ?array
    {
        $raw = $this->redis()->get($key);
        $value = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($value) ? $value : null;
    }

    public function forget(string $key): void
    {
        $this->redis()->del($key);
    }

    private function redis(): Connection
    {
        return Redis::connection($this->connectionName);
    }

    /**
     * @param  array<string, scalar|null>  $fields
     * @return array<string, string>
     */
    private static function stringify(array $fields): array
    {
        $values = [];

        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }

            $values[$name] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $values;
    }
}
