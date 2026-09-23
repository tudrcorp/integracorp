<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Estado de las colas y de los trabajos fallidos para el monitor en vivo.
 *
 * Por cada cola: pendientes, en proceso, programados y antigüedad del
 * pendiente más viejo. Una cola cuyo pendiente más viejo supera el umbral
 * está atascada: casi siempre significa que ningún worker la escucha.
 *
 * Con el driver `database` todo sale de una sola consulta agrupada, y las
 * colas que no están en la lista configurada se descubren solas.
 */
final class QueueHealth
{
    /** Filas de fallidos recientes que se leen para agrupar causas. */
    private const FAILED_SAMPLE = 500;

    /**
     * @return array{
     *     queues: list<array{name: string, pending: int|null, reserved: int|null, delayed: int|null, oldest_seconds: int|null, stuck: bool}>,
     *     pending_total: int,
     *     stuck: list<string>,
     *     stuck_after_minutes: int,
     *     worker_command: string,
     *     failed: array{total: int|null, last_24h: int|null, last_7d: int|null, top: list<array{job: string, reason: string, count: int, last_at: string|null}>}
     * }
     */
    public static function measure(): array
    {
        $stuckAfter = max(1, (int) config('live-presence.queues.stuck_after_minutes', 30));
        $configured = self::configuredQueues();

        try {
            $connection = Queue::connection();
        } catch (Throwable) {
            $connection = null;
        }

        $rows = $connection instanceof DatabaseQueue
            ? self::measureDatabase($connection, $configured)
            : self::measureNative($connection, $configured);

        $queues = [];
        $pendingTotal = 0;
        $stuck = [];

        foreach ($rows as $name => $row) {
            $isStuck = $row['oldest_seconds'] !== null && $row['oldest_seconds'] >= $stuckAfter * 60;

            if ($isStuck) {
                $stuck[] = $name;
            }

            $pendingTotal += (int) $row['pending'];
            $queues[] = ['name' => $name, ...$row, 'stuck' => $isStuck];
        }

        /** Las atascadas y las que tienen trabajo arriba; luego el orden configurado. */
        usort($queues, static fn (array $a, array $b): int => [$b['stuck'], (int) $b['pending'] > 0] <=> [$a['stuck'], (int) $a['pending'] > 0]);

        return [
            'queues' => $queues,
            'pending_total' => $pendingTotal,
            'stuck' => $stuck,
            'stuck_after_minutes' => $stuckAfter,
            'worker_command' => self::workerCommand(array_keys($rows)),
            'failed' => self::failedJobs(),
        ];
    }

    /**
     * «45 s», «12 min», «3 h 5 min», «26 días».
     */
    public static function ageLabel(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min';
        }

        if ($seconds < 86400) {
            $minutes = intdiv($seconds % 3600, 60);

            return intdiv($seconds, 3600).' h'.($minutes > 0 ? ' '.$minutes.' min' : '');
        }

        $days = intdiv($seconds, 86400);

        return $days.' '.($days === 1 ? 'día' : 'días');
    }

    /**
     * Comando del worker que atiende todas las colas conocidas, en orden de prioridad.
     *
     * @param  list<string>  $queues
     */
    public static function workerCommand(array $queues = []): string
    {
        $ordered = array_values(array_unique([...self::configuredQueues(), ...$queues]));

        return 'php artisan queue:work --queue='.implode(',', $ordered);
    }

    /**
     * @return list<string>
     */
    public static function configuredQueues(): array
    {
        $queues = array_map('strval', (array) config('live-presence.queues.names', ['default']));
        $queues = array_values(array_unique(array_filter(array_map('trim', $queues))));

        return $queues === [] ? ['default'] : $queues;
    }

    /**
     * @param  list<string>  $configured
     * @return array<string, array{pending: int|null, reserved: int|null, delayed: int|null, oldest_seconds: int|null}>
     */
    private static function measureDatabase(DatabaseQueue $queue, array $configured): array
    {
        $rows = array_fill_keys($configured, ['pending' => 0, 'reserved' => 0, 'delayed' => 0, 'oldest_seconds' => null]);
        $now = now()->getTimestamp();

        try {
            $grouped = $queue->getDatabase()
                ->table((string) config('queue.connections.'.config('queue.default').'.table', 'jobs'))
                ->selectRaw('queue')
                ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) AS pending_count', [$now])
                ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS reserved_count')
                ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) AS delayed_count', [$now])
                ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at END) AS oldest_available', [$now])
                ->groupBy('queue')
                ->get();
        } catch (Throwable) {
            return array_map(static fn (): array => ['pending' => null, 'reserved' => null, 'delayed' => null, 'oldest_seconds' => null], $rows);
        }

        foreach ($grouped as $row) {
            $oldest = $row->oldest_available !== null ? (int) $row->oldest_available : null;

            $rows[(string) $row->queue] = [
                'pending' => (int) $row->pending_count,
                'reserved' => (int) $row->reserved_count,
                'delayed' => (int) $row->delayed_count,
                'oldest_seconds' => $oldest !== null ? max(0, $now - $oldest) : null,
            ];
        }

        return $rows;
    }

    /**
     * Redis y demás drivers: métodos nativos de Laravel, solo sobre las colas configuradas.
     *
     * @param  list<string>  $configured
     * @return array<string, array{pending: int|null, reserved: int|null, delayed: int|null, oldest_seconds: int|null}>
     */
    private static function measureNative(?QueueContract $queue, array $configured): array
    {
        $rows = [];
        $now = now()->getTimestamp();

        foreach ($configured as $name) {
            $row = ['pending' => null, 'reserved' => null, 'delayed' => null, 'oldest_seconds' => null];

            if ($queue !== null) {
                try {
                    $row['pending'] = (int) (method_exists($queue, 'pendingSize') ? $queue->pendingSize($name) : $queue->size($name));
                    $row['reserved'] = method_exists($queue, 'reservedSize') ? (int) $queue->reservedSize($name) : null;
                    $row['delayed'] = method_exists($queue, 'delayedSize') ? (int) $queue->delayedSize($name) : null;
                    $created = method_exists($queue, 'creationTimeOfOldestPendingJob') ? $queue->creationTimeOfOldestPendingJob($name) : null;
                    $row['oldest_seconds'] = is_numeric($created) ? max(0, $now - (int) $created) : null;
                } catch (Throwable) {
                }
            }

            $rows[$name] = $row;
        }

        return $rows;
    }

    /**
     * @return array{total: int|null, last_24h: int|null, last_7d: int|null, top: list<array{job: string, reason: string, count: int, last_at: string|null}>}
     */
    private static function failedJobs(): array
    {
        $failed = ['total' => null, 'last_24h' => null, 'last_7d' => null, 'top' => []];

        try {
            $table = DB::connection(config('queue.failed.database'))->table((string) config('queue.failed.table', 'failed_jobs'));

            $counts = (clone $table)
                ->selectRaw('COUNT(*) AS total_count')
                ->selectRaw('SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END) AS last_day', [now()->subDay()])
                ->selectRaw('SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END) AS last_week', [now()->subDays(7)])
                ->first();

            $failed['total'] = (int) ($counts->total_count ?? 0);
            $failed['last_24h'] = (int) ($counts->last_day ?? 0);
            $failed['last_7d'] = (int) ($counts->last_week ?? 0);

            /** Solo el comienzo del payload y de la excepción: nunca los cuerpos completos. */
            $sample = (clone $table)
                ->selectRaw('SUBSTR(payload, 1, 400) AS payload_head')
                ->selectRaw('SUBSTR(exception, 1, 400) AS exception_head')
                ->addSelect('failed_at')
                ->where('failed_at', '>=', now()->subDays(7))
                ->orderByDesc('failed_at')
                ->limit(self::FAILED_SAMPLE)
                ->get();

            $failed['top'] = self::topCauses($sample->map(static fn (object $row): array => [
                'payload' => (string) $row->payload_head,
                'exception' => (string) $row->exception_head,
                'failed_at' => $row->failed_at !== null ? (string) $row->failed_at : null,
            ])->all());
        } catch (Throwable) {
        }

        return $failed;
    }

    /**
     * Agrupa fallidos por trabajo y primera línea de la excepción.
     *
     * @param  list<array{payload: string, exception: string, failed_at: string|null}>  $rows  ordenadas del más reciente al más viejo
     * @return list<array{job: string, reason: string, count: int, last_at: string|null}>
     */
    public static function topCauses(array $rows, int $limit = 5): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $job = preg_match('/"displayName"\s*:\s*"([^"]+)"/', $row['payload'], $match) === 1
                ? class_basename(stripslashes($match[1]))
                : 'Desconocido';

            $reason = self::reason($row['exception']);
            $key = $job.'|'.$reason;

            $groups[$key] ??= ['job' => $job, 'reason' => $reason, 'count' => 0, 'last_at' => $row['failed_at']];
            $groups[$key]['count']++;
        }

        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($groups, 0, $limit);
    }

    private static function reason(string $exception): string
    {
        $line = trim(strtok($exception, "\n") ?: '');

        /** Quita el «in /ruta/archivo.php:123» y el espacio de nombres de la excepción. */
        $line = (string) preg_replace('/\s+in\s+\/\S+:\d+$/', '', $line);
        $line = (string) preg_replace('/^[A-Za-z_\\\\]+\\\\([A-Za-z_]+)(:)/', '$1$2', $line);

        return $line === '' ? 'Sin detalle' : mb_strimwidth($line, 0, 160, '…');
    }
}
