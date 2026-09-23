<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Closure;
use Throwable;

/**
 * Trabajos de la cola con el driver `redis`, con la estructura de Laravel:
 * - `queues:{cola}`            lista de pendientes (payload JSON).
 * - `queues:{cola}:delayed`    conjunto ordenado de programados (puntaje = cuándo se liberan).
 * - `queues:{cola}:reserved`   conjunto ordenado de reservados (puntaje = cuándo vence la reserva).
 *
 * Quitar es atómico por trabajo: LREM / ZREM con el payload exacto. Si un
 * worker ya lo tomó, Redis devuelve 0 y el trabajo no se toca.
 */
final class RedisQueueJobStore implements QueueJobStore
{
    private const LIMIT = 20000;

    /**
     * @param  object  $redis  conexión de Redis de Laravel (lrange, lrem, zrangebyscore, zrem)
     */
    public function __construct(
        private readonly object $redis,
        private readonly Closure $keyFor,
    ) {}

    public function entries(string $queue, bool $fullPayload = false): array
    {
        $key = ($this->keyFor)($queue);
        $entries = [];

        foreach ((array) $this->redis->lrange($key, 0, self::LIMIT - 1) as $payload) {
            $entries[] = $this->entry('pending', (string) $payload, $queue, null);
        }

        foreach (['delayed', 'reserved'] as $bucket) {
            $members = (array) $this->redis->zrangebyscore($key.':'.$bucket, '-inf', '+inf', ['withscores' => true, 'limit' => [0, self::LIMIT]]);

            foreach ($members as $payload => $score) {
                $entries[] = $this->entry($bucket, (string) $payload, $queue, (int) $score);
            }
        }

        return $entries;
    }

    public function remove(string $queue, array $entries, ?Closure $archive = null, ?Closure $unarchive = null): int
    {
        $key = ($this->keyFor)($queue);
        $removed = 0;

        foreach ($entries as $entry) {
            $payload = (string) $entry['ref'];
            /** Primero se archiva y luego se quita: si quitar falla, se deshace el archivo; nunca se pierde un trabajo. */
            $archived = $archive !== null ? $archive($payload, $queue) : null;

            try {
                $count = match ($entry['bucket']) {
                    'pending' => (int) $this->redis->lrem($key, 1, $payload),
                    default => (int) $this->redis->zrem($key.':'.$entry['bucket'], $payload),
                };
            } catch (Throwable $exception) {
                if ($unarchive !== null && $archived !== null) {
                    $unarchive($archived);
                }

                throw $exception;
            }

            if ($count > 0) {
                $removed++;
            } elseif ($unarchive !== null && $archived !== null) {
                $unarchive($archived);
            }
        }

        return $removed;
    }

    /**
     * @return array{bucket: string, ref: string, queue: string, payload: string, created_at: int|null, available_at: int|null, reserved_at: int|null, reserved_until: int|null}
     */
    private function entry(string $bucket, string $payload, string $queue, ?int $score): array
    {
        $decoded = json_decode($payload, true);
        $createdAt = is_array($decoded) && isset($decoded['createdAt']) ? (int) $decoded['createdAt'] : null;

        return [
            'bucket' => $bucket,
            'ref' => $payload,
            'queue' => $queue,
            'payload' => $payload,
            'created_at' => $createdAt,
            'available_at' => $bucket === 'delayed' ? $score : $createdAt,
            'reserved_at' => null,
            'reserved_until' => $bucket === 'reserved' ? $score : null,
        ];
    }
}
