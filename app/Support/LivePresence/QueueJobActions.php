<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\FailedJob;
use App\Support\SecurityAudit;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Liberar una cola atascada: sacar trabajos que esperan (o que quedaron
 * colgados) para que el resto fluya.
 *
 * Dos formas, a elección del analista:
 * - Mover a fallidos (recomendado): el trabajo sale de la cola pero queda en
 *   «Colas y errores», donde se puede reintentar cuando la cola esté sana.
 * - Eliminar: se borra sin rastro del trabajo (sí queda en la auditoría).
 *
 * Solo aplica al driver `database`. Los pendientes se sacan únicamente si
 * nadie los tomó entre la lectura y el borrado (`reserved_at` nulo), así no se
 * pisa un trabajo que un worker acaba de empezar.
 */
final class QueueJobActions
{
    public const SCOPE_STUCK = 'stuck';

    public const SCOPE_PENDING = 'pending';

    public const SCOPE_ZOMBIES = 'zombies';

    public const SCOPE_ALL = 'all';

    public const MODE_MOVE = 'move';

    public const MODE_DELETE = 'delete';

    private const CHUNK = 500;

    /**
     * @return array<string, string>
     */
    public static function scopeLabels(): array
    {
        return [
            self::SCOPE_STUCK => 'Solo los atascados (esperan más de '.self::stuckAfterMinutes().' min)',
            self::SCOPE_PENDING => 'Todos los que esperan',
            self::SCOPE_ZOMBIES => 'Los colgados (reservados hace más de '.QueueHealth::ageLabel(self::retryAfter()).')',
            self::SCOPE_ALL => 'Todo lo de la cola (incluye programados y colgados)',
        ];
    }

    public static function isSupported(): bool
    {
        try {
            return Queue::connection() instanceof DatabaseQueue;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Cuántos trabajos tocaría la operación, por alcance.
     *
     * @return array<string, int>
     */
    public static function counts(string $queue, ?string $jobClass = null): array
    {
        $counts = [];

        foreach (array_keys(self::scopeLabels()) as $scope) {
            $counts[$scope] = count(self::ids($queue, $scope, $jobClass));
        }

        return $counts;
    }

    /**
     * Tipos de trabajo presentes en la cola, para filtrar.
     *
     * @return array<string, string> clase => «N × Nombre»
     */
    public static function jobClasses(string $queue): array
    {
        $classes = [];

        try {
            self::table()->where('queue', $queue)->select('id')->selectRaw('SUBSTR(payload, 1, 300) AS payload_head')->orderBy('id')->limit(20000)
                ->get()
                ->each(function (object $row) use (&$classes): void {
                    $class = FailedJob::jobClassFromPayload((string) $row->payload_head);
                    $classes[$class] = ($classes[$class] ?? 0) + 1;
                });
        } catch (Throwable) {
            return [];
        }

        arsort($classes);
        $options = [];

        foreach ($classes as $class => $count) {
            $options[$class] = $count.' × '.class_basename($class);
        }

        return $options;
    }

    /**
     * @return array{affected: int, moved: int, deleted: int}
     *
     * @throws InvalidArgumentException
     */
    public static function release(string $queue, string $scope, string $mode, ?string $jobClass = null, string $reason = '', ?string $actor = null): array
    {
        if (! self::isSupported()) {
            throw new InvalidArgumentException('Liberar colas solo está disponible con el driver de colas «database».');
        }

        if (! array_key_exists($scope, self::scopeLabels()) || ! in_array($mode, [self::MODE_MOVE, self::MODE_DELETE], true)) {
            throw new InvalidArgumentException('La operación no es válida.');
        }

        $queue = trim($queue);

        if ($queue === '') {
            throw new InvalidArgumentException('Indique la cola.');
        }

        $actor ??= 'system';
        $ids = self::ids($queue, $scope, $jobClass);
        $moved = 0;
        $deleted = 0;

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            self::connection()->transaction(function () use ($chunk, $scope, $mode, $reason, $actor, &$moved, &$deleted): void {
                $query = self::table()->whereIn('id', $chunk)->lockForUpdate();

                /** Un pendiente que un worker tomó mientras tanto ya no se toca. */
                if (in_array($scope, [self::SCOPE_STUCK, self::SCOPE_PENDING], true)) {
                    $query->whereNull('reserved_at');
                }

                $rows = $query->get(['id', 'queue', 'payload']);

                if ($rows->isEmpty()) {
                    return;
                }

                if ($mode === self::MODE_MOVE) {
                    $failed = [];
                    $now = now();

                    foreach ($rows as $row) {
                        $failed[] = [
                            'uuid' => self::uuidFor((string) $row->payload),
                            'connection' => (string) config('queue.default'),
                            'queue' => (string) $row->queue,
                            'payload' => (string) $row->payload,
                            'exception' => 'App\Support\LivePresence\QueueJobActions: Sacado de la cola a mano desde el monitor por '.$actor.'.'
                                .($reason !== '' ? ' Motivo: '.Str::limit(trim($reason), 500) : '')
                                .' in '.__FILE__.':'.__LINE__,
                            'failed_at' => $now,
                        ];
                    }

                    FailedJob::query()->insert($failed);
                    $moved += count($failed);
                } else {
                    $deleted += count($rows);
                }

                self::table()->whereIn('id', $rows->pluck('id')->all())->delete();
            });
        }

        $result = ['affected' => $moved + $deleted, 'moved' => $moved, 'deleted' => $deleted];

        FailedJobCatalog::forgetCache();

        try {
            SecurityAudit::log('AUDIT_LIVE_QUEUE_RELEASED', 'live-presence.queues', [
                'queue' => $queue,
                'scope' => $scope,
                'mode' => $mode,
                'job_class' => $jobClass,
                'reason' => $reason,
                ...$result,
            ]);
        } catch (Throwable) {
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private static function ids(string $queue, string $scope, ?string $jobClass): array
    {
        try {
            $now = now()->getTimestamp();
            $query = self::table()->where('queue', $queue);

            match ($scope) {
                self::SCOPE_STUCK => $query->whereNull('reserved_at')->where('available_at', '<=', $now - self::stuckAfterMinutes() * 60),
                self::SCOPE_PENDING => $query->whereNull('reserved_at')->where('available_at', '<=', $now),
                self::SCOPE_ZOMBIES => $query->whereNotNull('reserved_at')->where('reserved_at', '<', $now - self::retryAfter()),
                default => $query,
            };

            if ($jobClass === null || $jobClass === '') {
                return array_map('intval', $query->orderBy('id')->pluck('id')->all());
            }

            $ids = [];

            $query->select('id')->selectRaw('SUBSTR(payload, 1, 300) AS payload_head')->orderBy('id')
                ->get()
                ->each(function (object $row) use ($jobClass, &$ids): void {
                    if (FailedJob::jobClassFromPayload((string) $row->payload_head) === $jobClass) {
                        $ids[] = (int) $row->id;
                    }
                });

            return $ids;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * El UUID del trabajo si todavía no está en fallidos; si no, uno nuevo.
     */
    private static function uuidFor(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $uuid = is_array($decoded) ? (string) ($decoded['uuid'] ?? '') : '';

        if ($uuid === '' || FailedJob::query()->where('uuid', $uuid)->exists()) {
            return (string) Str::uuid();
        }

        return $uuid;
    }

    private static function connection(): ConnectionInterface
    {
        /** @var DatabaseQueue $queue */
        $queue = Queue::connection();

        return $queue->getDatabase();
    }

    private static function table(): Builder
    {
        return self::connection()->table((string) config('queue.connections.'.config('queue.default').'.table', 'jobs'));
    }

    private static function stuckAfterMinutes(): int
    {
        return max(1, (int) config('live-presence.queues.stuck_after_minutes', 30));
    }

    private static function retryAfter(): int
    {
        return max(60, (int) config('queue.connections.'.config('queue.default').'.retry_after', 90));
    }
}
