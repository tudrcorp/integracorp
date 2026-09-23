<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Trabajos de la cola con el driver `database` (tabla `jobs`).
 */
final class DatabaseQueueJobStore implements QueueJobStore
{
    private const LIMIT = 20000;

    private const CHUNK = 500;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
    ) {}

    public function entries(string $queue, bool $fullPayload = false): array
    {
        $now = now()->getTimestamp();
        $query = $this->table()->where('queue', $queue)->select('id', 'queue', 'reserved_at', 'available_at', 'created_at')->orderBy('id')->limit(self::LIMIT);

        $fullPayload ? $query->addSelect('payload') : $query->selectRaw('SUBSTR(payload, 1, 300) AS payload');

        $entries = [];

        foreach ($query->get() as $row) {
            $entries[] = [
                'bucket' => $row->reserved_at !== null ? 'reserved' : ((int) $row->available_at > $now ? 'delayed' : 'pending'),
                'ref' => (int) $row->id,
                'queue' => (string) $row->queue,
                'payload' => (string) $row->payload,
                'created_at' => $row->created_at !== null ? (int) $row->created_at : null,
                'available_at' => (int) $row->available_at,
                'reserved_at' => $row->reserved_at !== null ? (int) $row->reserved_at : null,
                'reserved_until' => null,
            ];
        }

        return $entries;
    }

    public function remove(string $queue, array $entries, ?Closure $archive = null, ?Closure $unarchive = null): int
    {
        $removed = 0;

        foreach (array_chunk($entries, self::CHUNK) as $chunk) {
            $pendingIds = array_map(static fn (array $entry): int => (int) $entry['ref'], array_filter($chunk, static fn (array $entry): bool => $entry['bucket'] !== 'reserved'));
            $reservedIds = array_map(static fn (array $entry): int => (int) $entry['ref'], array_filter($chunk, static fn (array $entry): bool => $entry['bucket'] === 'reserved'));

            $this->connection->transaction(function () use ($queue, $pendingIds, $reservedIds, $archive, &$removed): void {
                $rows = collect();

                if ($pendingIds !== []) {
                    /** Un pendiente que un worker tomó mientras tanto ya no se toca. */
                    $rows = $rows->merge($this->table()->where('queue', $queue)->whereIn('id', $pendingIds)->whereNull('reserved_at')->lockForUpdate()->get(['id', 'queue', 'payload']));
                }

                if ($reservedIds !== []) {
                    $rows = $rows->merge($this->table()->where('queue', $queue)->whereIn('id', $reservedIds)->whereNotNull('reserved_at')->lockForUpdate()->get(['id', 'queue', 'payload']));
                }

                if ($rows->isEmpty()) {
                    return;
                }

                if ($archive !== null) {
                    foreach ($rows as $row) {
                        $archive((string) $row->payload, (string) $row->queue);
                    }
                }

                $removed += $this->table()->whereIn('id', $rows->pluck('id')->all())->delete();
            });
        }

        return $removed;
    }

    private function table(): Builder
    {
        return $this->connection->table($this->table);
    }
}
