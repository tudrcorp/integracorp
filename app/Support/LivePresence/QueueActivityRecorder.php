<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Throwable;

/**
 * Lo que los workers cuentan de sí mismos, para medir en vez de adivinar.
 *
 * - Latido: en cada vuelta el worker dice quién es y qué colas escucha. Así
 *   «nadie escucha la cola X» se detecta al instante, no a los 30 minutos.
 * - Rendimiento: procesados y fallidos por minuto y por cola, y duración por
 *   tipo de trabajo (media, máxima, última).
 *
 * Corre dentro del worker. Todo va a Redis (o la caché en desarrollo) y
 * cualquier fallo se traga: medir nunca puede tumbar el trabajo medido.
 * Los trabajos síncronos (conexión `sync`) no pasan por ningún worker y no se cuentan.
 */
final class QueueActivityRecorder
{
    public const PREFIX = 'lp:q:';

    /** Segundos entre latidos del mismo worker. */
    private const BEAT_EVERY = 10;

    private const DAY = 86400;

    private static int $lastBeat = 0;

    private static int $processedSinceBeat = 0;

    /**
     * @var array<string, float> id del trabajo → microtime de inicio
     */
    private static array $started = [];

    /**
     * @var array{job: string, queue: string, connection: string}|null
     */
    private static ?array $current = null;

    public static function onLooping(Looping $event): void
    {
        if ($event->connectionName === 'sync' || time() - self::$lastBeat < self::BEAT_EVERY) {
            return;
        }

        self::$lastBeat = time();
        $processed = self::$processedSinceBeat;
        self::$processedSinceBeat = 0;

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($event, $processed): void {
            $host = (string) (gethostname() ?: 'servidor');
            $queues = array_values(array_filter(array_map('trim', explode(',', (string) $event->queue))));
            $id = substr(sha1($host.'|'.$event->connectionName.'|'.implode(',', $queues)), 0, 12);
            $key = self::PREFIX.'w:'.$id;
            $current = $store->getValue($key) ?? [];

            $store->putValue($key, [
                'id' => $id,
                'host' => $host,
                'pid' => getmypid() ?: null,
                'connection' => (string) $event->connectionName,
                'queues' => $queues,
                'first_beat' => (int) ($current['first_beat'] ?? time()),
                'last_beat' => time(),
                'processed' => (int) ($current['processed'] ?? 0) + $processed,
            ], self::DAY);
            $store->scoreMember(self::PREFIX.'workers', $id, 1, self::DAY);
        });
    }

    public static function onProcessing(JobProcessing $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        try {
            self::$started[(string) $event->job->getJobId()] = microtime(true);
            self::$current = ['job' => $event->job->resolveName(), 'queue' => $event->job->getQueue(), 'connection' => $event->connectionName];
        } catch (Throwable) {
        }
    }

    public static function onProcessed(JobProcessed $event): void
    {
        self::finish($event->connectionName, $event, false);
    }

    public static function onFailed(JobFailed $event): void
    {
        self::finish($event->connectionName, $event, true);
    }

    /**
     * Trabajo que el worker procesa ahora mismo: contexto para el registro de errores.
     *
     * @return array{job: string, queue: string, connection: string}|null
     */
    public static function currentJob(): ?array
    {
        return self::$current;
    }

    public static function metricKey(string $metric, string $queue, string $minute): string
    {
        return self::PREFIX.'m:'.$metric.':'.$queue.':'.$minute;
    }

    /**
     * Workers con latido reciente.
     *
     * @return array{known: bool, alive: list<array{id: string, host: string, pid: int|null, connection: string, queues: list<string>, last_beat: int, last_beat_ago: string, uptime: string, processed: int}>}
     */
    public static function workers(?LivePresenceRepository $store = null): array
    {
        try {
            $store ??= LivePresenceStore::repository();
            $ids = array_keys($store->topMembers(self::PREFIX.'workers', 100));
        } catch (Throwable) {
            return ['known' => false, 'alive' => []];
        }

        $aliveWindow = max(20, (int) config('live-presence.queues.worker_alive_seconds', 60));
        $now = time();
        $alive = [];

        foreach ($ids as $id) {
            $worker = $store->getValue(self::PREFIX.'w:'.$id);

            if ($worker === null || (int) ($worker['last_beat'] ?? 0) < $now - $aliveWindow) {
                continue;
            }

            $alive[] = [
                'id' => (string) $id,
                'host' => (string) ($worker['host'] ?? ''),
                'pid' => isset($worker['pid']) ? (int) $worker['pid'] : null,
                'connection' => (string) ($worker['connection'] ?? ''),
                'queues' => array_values(array_map('strval', (array) ($worker['queues'] ?? []))),
                'last_beat' => (int) $worker['last_beat'],
                'last_beat_ago' => LiveActivitySnapshot::ago($now - (int) $worker['last_beat']),
                'uptime' => QueueHealth::ageLabel(max(0, $now - (int) ($worker['first_beat'] ?? $now))),
                'processed' => (int) ($worker['processed'] ?? 0),
            ];
        }

        /** «Conocidos» = alguna vez latió un worker con este código; si no, no se puede afirmar que no haya. */
        return ['known' => $ids !== [], 'alive' => $alive];
    }

    /**
     * Duración por tipo de trabajo, de los más lentos a los más rápidos.
     *
     * @return list<array{job: string, count: int, failed: int, avg_ms: int, max_ms: int, last_ms: int, last_ago: string}>
     */
    public static function jobStats(int $limit = 15): array
    {
        try {
            $store = LivePresenceStore::repository();
            $classes = array_keys($store->topMembers(self::PREFIX.'classes', 60));
        } catch (Throwable) {
            return [];
        }

        $now = time();
        $stats = [];

        foreach ($classes as $class) {
            $row = $store->getValue(self::PREFIX.'c:'.sha1((string) $class));

            if ($row === null) {
                continue;
            }

            $count = max(1, (int) ($row['count'] ?? 0));
            $stats[] = [
                'job' => class_basename((string) $class),
                'count' => (int) ($row['count'] ?? 0),
                'failed' => (int) ($row['failed'] ?? 0),
                'avg_ms' => (int) round(((float) ($row['total_ms'] ?? 0)) / $count),
                'max_ms' => (int) ($row['max_ms'] ?? 0),
                'last_ms' => (int) ($row['last_ms'] ?? 0),
                'last_ago' => LiveActivitySnapshot::ago($now - (int) ($row['last_at'] ?? $now)),
            ];
        }

        usort($stats, static fn (array $a, array $b): int => $b['avg_ms'] <=> $a['avg_ms']);

        return array_slice($stats, 0, $limit);
    }

    /**
     * Para pruebas.
     */
    public static function reset(): void
    {
        self::$lastBeat = 0;
        self::$processedSinceBeat = 0;
        self::$started = [];
        self::$current = null;
    }

    private static function finish(string $connection, JobProcessed|JobFailed $event, bool $failed): void
    {
        if ($connection === 'sync') {
            return;
        }

        self::$current = null;

        try {
            $jobId = (string) $event->job->getJobId();
            $class = $event->job->resolveName();
            $queue = (string) $event->job->getQueue();
        } catch (Throwable) {
            return;
        }

        $started = self::$started[$jobId] ?? null;
        unset(self::$started[$jobId]);
        $milliseconds = $started !== null ? (int) round((microtime(true) - $started) * 1000) : null;

        if (! $failed) {
            self::$processedSinceBeat++;
        }

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($class, $queue, $milliseconds, $failed): void {
            $minute = SecurityMonitor::minute();
            $metric = $failed ? 'failed' : 'processed';

            $store->increment(self::metricKey($metric, $queue, $minute), 7200);
            $store->increment(self::metricKey($metric, '*', $minute), 7200);

            $key = self::PREFIX.'c:'.sha1($class);
            $row = $store->getValue($key) ?? [];

            $store->putValue($key, [
                'count' => (int) ($row['count'] ?? 0) + ($failed ? 0 : 1),
                'failed' => (int) ($row['failed'] ?? 0) + ($failed ? 1 : 0),
                'total_ms' => (float) ($row['total_ms'] ?? 0) + ($failed ? 0 : (float) ($milliseconds ?? 0)),
                'max_ms' => max((int) ($row['max_ms'] ?? 0), $failed ? 0 : (int) ($milliseconds ?? 0)),
                'last_ms' => $failed ? (int) ($row['last_ms'] ?? 0) : (int) ($milliseconds ?? 0),
                'last_at' => time(),
            ], self::DAY);
            $store->scoreMember(self::PREFIX.'classes', $class, 1, self::DAY);
        });
    }
}
