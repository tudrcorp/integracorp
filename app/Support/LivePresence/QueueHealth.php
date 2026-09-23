<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\FailedJob;
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
 *
 * Con el latido de los workers (QueueActivityRecorder) se sabe además quién
 * escucha cada cola: una cola con trabajo y sin nadie que la escuche se marca
 * al instante, sin esperar el umbral de atascada.
 */
final class QueueHealth
{
    /** Filas de fallidos recientes que se leen para agrupar causas. */
    private const FAILED_SAMPLE = 500;

    /** Pendientes que se leen para saber qué tipo de trabajo espera en cada cola. */
    private const PENDING_SAMPLE = 3000;

    public const STATUS_LABELS = [
        'unattended' => 'sin worker',
        'stuck' => 'atascada',
        'zombie' => 'trabajos colgados',
        'busy' => 'procesando',
        'ok' => 'al día',
        'unknown' => 'sin lectura',
    ];

    /** Minutos de la ventana de rendimiento que se grafica. */
    public const THROUGHPUT_MINUTES = 30;

    /**
     * Estado de todas las colas: por cola (pendientes, en proceso, programados,
     * zombis, quién la escucha, estado y consejo), workers vivos, rendimiento
     * de los últimos 30 min y trabajos fallidos agrupados por causa.
     *
     * @return array<string, mixed>
     */
    public static function measure(): array
    {
        $stuckAfter = max(1, (int) config('live-presence.queues.stuck_after_minutes', 30));
        $unattendedAfter = max(0, (int) config('live-presence.queues.unattended_after_seconds', 60));
        $configured = self::configuredQueues();

        try {
            $connection = Queue::connection();
        } catch (Throwable) {
            $connection = null;
        }

        $rows = $connection instanceof DatabaseQueue
            ? self::measureDatabase($connection, $configured)
            : self::measureNative($connection, $configured);

        $workers = QueueActivityRecorder::workers();
        $listeners = [];

        foreach ($workers['alive'] as $worker) {
            foreach ($worker['queues'] as $queueName) {
                $listeners[$queueName] = ($listeners[$queueName] ?? 0) + 1;
            }
        }

        $throughput = self::throughput(array_keys($rows));
        $pendingByClass = $connection instanceof DatabaseQueue && array_sum(array_map(static fn (array $row): int => (int) $row['pending'], $rows)) > 0
            ? self::pendingByClass($connection)
            : [];

        $queues = [];
        $pendingTotal = 0;
        $stuck = [];
        $unattended = [];
        $zombieTotal = 0;

        foreach ($rows as $name => $row) {
            $isStuck = $row['oldest_seconds'] !== null && $row['oldest_seconds'] >= $stuckAfter * 60;
            $listenerCount = $listeners[$name] ?? 0;
            $isUnattended = $workers['known']
                && $listenerCount === 0
                && (int) $row['pending'] > 0
                && (int) ($row['oldest_seconds'] ?? 0) >= $unattendedAfter;
            $zombies = (int) ($row['zombies'] ?? 0);

            if ($isStuck) {
                $stuck[] = $name;
            }

            if ($isUnattended) {
                $unattended[] = $name;
            }

            $zombieTotal += $zombies;
            $pendingTotal += (int) $row['pending'];

            $status = match (true) {
                $row['pending'] === null => 'unknown',
                $isUnattended => 'unattended',
                $isStuck => 'stuck',
                $zombies > 0 => 'zombie',
                (int) $row['pending'] > 0 || (int) ($row['reserved'] ?? 0) > 0 => 'busy',
                default => 'ok',
            };

            $queues[] = [
                'name' => $name,
                ...$row,
                'stuck' => $isStuck,
                'unattended' => $isUnattended,
                'listeners' => $listenerCount,
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status],
                'advice' => self::advice($status, $name, $row, $stuckAfter),
                'processed_30m' => array_sum($throughput['by_queue'][$name]['processed'] ?? []),
                'failed_30m' => array_sum($throughput['by_queue'][$name]['failed'] ?? []),
                'pending_by_class' => array_slice($pendingByClass[$name] ?? [], 0, 5, true),
            ];
        }

        /** Primero lo que exige acción; luego las que tienen trabajo; luego el orden configurado. */
        $rank = ['unattended' => 0, 'stuck' => 1, 'zombie' => 2, 'busy' => 3, 'unknown' => 4, 'ok' => 5];
        usort($queues, static fn (array $a, array $b): int => [$rank[$a['status']], (int) $b['pending'] > 0 ? 0 : 1] <=> [$rank[$b['status']], (int) $a['pending'] > 0 ? 0 : 1]);

        return [
            'queues' => $queues,
            'pending_total' => $pendingTotal,
            'stuck' => $stuck,
            'unattended' => $unattended,
            'zombies' => $zombieTotal,
            'stuck_after_minutes' => $stuckAfter,
            'worker_command' => self::workerCommand(array_keys($rows)),
            'workers' => $workers,
            'throughput' => [
                'processed' => $throughput['processed'],
                'failed' => $throughput['failed'],
                'processed_last_minute' => (int) end($throughput['processed']),
                'processed_30m' => array_sum($throughput['processed']),
                'failed_30m' => array_sum($throughput['failed']),
            ],
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
        $rows = array_fill_keys($configured, ['pending' => 0, 'reserved' => 0, 'delayed' => 0, 'oldest_seconds' => null, 'zombies' => 0, 'running_seconds' => null]);
        $now = now()->getTimestamp();
        /** Reservado hace más de retry_after: el worker que lo tomó casi seguro murió. */
        $retryAfter = max(60, (int) config('queue.connections.'.config('queue.default').'.retry_after', 90));

        try {
            $grouped = $queue->getDatabase()
                ->table((string) config('queue.connections.'.config('queue.default').'.table', 'jobs'))
                ->selectRaw('queue')
                ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) AS pending_count', [$now])
                ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS reserved_count')
                ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) AS delayed_count', [$now])
                ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at END) AS oldest_available', [$now])
                ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL AND reserved_at < ? THEN 1 ELSE 0 END) AS zombie_count', [$now - $retryAfter])
                ->selectRaw('MIN(reserved_at) AS oldest_reserved')
                ->groupBy('queue')
                ->get();
        } catch (Throwable) {
            return array_map(static fn (): array => ['pending' => null, 'reserved' => null, 'delayed' => null, 'oldest_seconds' => null, 'zombies' => 0, 'running_seconds' => null], $rows);
        }

        foreach ($grouped as $row) {
            $oldest = $row->oldest_available !== null ? (int) $row->oldest_available : null;

            $rows[(string) $row->queue] = [
                'pending' => (int) $row->pending_count,
                'reserved' => (int) $row->reserved_count,
                'delayed' => (int) $row->delayed_count,
                'oldest_seconds' => $oldest !== null ? max(0, $now - $oldest) : null,
                'zombies' => (int) ($row->zombie_count ?? 0),
                'running_seconds' => $row->oldest_reserved !== null ? max(0, $now - (int) $row->oldest_reserved) : null,
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
            $row = ['pending' => null, 'reserved' => null, 'delayed' => null, 'oldest_seconds' => null, 'zombies' => 0, 'running_seconds' => null];

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
        $failed = ['total' => null, 'last_hour' => null, 'last_24h' => null, 'last_7d' => null, 'top' => [], 'groups' => []];

        try {
            $table = DB::connection(config('queue.failed.database'))->table((string) config('queue.failed.table', 'failed_jobs'));

            $counts = (clone $table)
                ->selectRaw('COUNT(*) AS total_count')
                ->selectRaw('SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END) AS last_hour', [now()->subHour()])
                ->selectRaw('SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END) AS last_day', [now()->subDay()])
                ->selectRaw('SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END) AS last_week', [now()->subDays(7)])
                ->first();

            $failed['total'] = (int) ($counts->total_count ?? 0);
            $failed['last_hour'] = (int) ($counts->last_hour ?? 0);
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

        $failed['groups'] = array_slice(FailedJobCatalog::groups(7), 0, 6);

        return $failed;
    }

    /**
     * Procesados y fallidos por minuto (global y por cola), de los contadores
     * que escriben los workers.
     *
     * @param  list<string>  $queues
     * @return array{processed: list<int>, failed: list<int>, by_queue: array<string, array{processed: list<int>, failed: list<int>}>}
     */
    private static function throughput(array $queues): array
    {
        $now = time();
        $minutes = [];

        for ($offset = self::THROUGHPUT_MINUTES - 1; $offset >= 0; $offset--) {
            $minutes[] = SecurityMonitor::minute($now - ($offset * 60));
        }

        $keys = [];

        foreach (['*', ...$queues] as $queue) {
            foreach (['processed', 'failed'] as $metric) {
                foreach ($minutes as $minute) {
                    $keys[] = QueueActivityRecorder::metricKey($metric, $queue, $minute);
                }
            }
        }

        try {
            $counters = LivePresenceStore::repository()->counters($keys);
        } catch (Throwable) {
            $counters = [];
        }

        $series = static fn (string $metric, string $queue): array => array_map(
            static fn (string $minute): int => (int) ($counters[QueueActivityRecorder::metricKey($metric, $queue, $minute)] ?? 0),
            $minutes,
        );

        $byQueue = [];

        foreach ($queues as $queue) {
            $byQueue[$queue] = ['processed' => $series('processed', $queue), 'failed' => $series('failed', $queue)];
        }

        return ['processed' => $series('processed', '*'), 'failed' => $series('failed', '*'), 'by_queue' => $byQueue];
    }

    /**
     * Qué tipo de trabajo espera en cada cola (driver database).
     *
     * @return array<string, array<string, int>> cola → trabajo → pendientes
     */
    private static function pendingByClass(DatabaseQueue $queue): array
    {
        try {
            $rows = $queue->getDatabase()
                ->table((string) config('queue.connections.'.config('queue.default').'.table', 'jobs'))
                ->select('queue')
                ->selectRaw('SUBSTR(payload, 1, 300) AS payload_head')
                ->whereNull('reserved_at')
                ->orderBy('id')
                ->limit(self::PENDING_SAMPLE)
                ->get();
        } catch (Throwable) {
            return [];
        }

        $classes = [];

        foreach ($rows as $row) {
            $job = class_basename(FailedJob::jobClassFromPayload((string) $row->payload_head));
            $classes[(string) $row->queue][$job] = ($classes[(string) $row->queue][$job] ?? 0) + 1;
        }

        foreach ($classes as $name => $counts) {
            arsort($counts);
            $classes[$name] = $counts;
        }

        return $classes;
    }

    /**
     * Qué pasa y qué hacer, en una frase, según el estado de la cola.
     *
     * @param  array<string, mixed>  $row
     */
    private static function advice(string $status, string $name, array $row, int $stuckAfter): string
    {
        $pending = (int) ($row['pending'] ?? 0);

        return match ($status) {
            'unattended' => $pending.' '.($pending === 1 ? 'trabajo espera' : 'trabajos esperan').' y ningún worker escucha «'.$name.'». Reinicie el worker con el comando de abajo, que incluye todas las colas.',
            'stuck' => 'El pendiente más viejo lleva '.self::ageLabel($row['oldest_seconds']).' (umbral '.$stuckAfter.' min). El worker no da abasto o está detenido.',
            'zombie' => (int) $row['zombies'].' '.((int) $row['zombies'] === 1 ? 'trabajo lleva' : 'trabajos llevan').' reservado más tiempo del permitido: el worker que los tomó probablemente murió. Volverán a la cola solos al vencer retry_after; si se repite, revise el timeout del trabajo.',
            'busy' => $pending > 0 ? $pending.' en espera, procesándose.' : 'Procesando.',
            'unknown' => 'No se pudo leer esta cola.',
            default => 'Sin trabajo pendiente.',
        };
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
