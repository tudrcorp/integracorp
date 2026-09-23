<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\FailedJob;
use App\Support\SecurityAudit;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Liberar una cola atascada: sacar trabajos que esperan (o que quedaron
 * colgados) para que el resto fluya. Funciona con los drivers `database` y `redis`.
 *
 * Dos formas, a elección del analista:
 * - Mover a fallidos (recomendado): el trabajo sale de la cola pero queda en
 *   «Colas y errores», donde se puede reintentar cuando la cola esté sana.
 * - Eliminar: se borra sin rastro del trabajo (sí queda en la auditoría).
 *
 * Un pendiente que un worker tomó entre la lectura y el borrado no se toca.
 */
final class QueueJobActions
{
    public const SCOPE_STUCK = 'stuck';

    public const SCOPE_DUPLICATES = 'duplicates';

    public const SCOPE_PENDING = 'pending';

    public const SCOPE_ZOMBIES = 'zombies';

    public const SCOPE_ALL = 'all';

    public const MODE_MOVE = 'move';

    public const MODE_DELETE = 'delete';

    private static ?QueueJobStore $store = null;

    private static bool $storeResolved = false;

    /**
     * @return array<string, string>
     */
    public static function scopeLabels(): array
    {
        return [
            self::SCOPE_STUCK => 'Solo los atascados (esperan más de '.self::stuckAfterMinutes().' min)',
            self::SCOPE_DUPLICATES => 'Repetidos idénticos (deja el más reciente de cada uno)',
            self::SCOPE_PENDING => 'Todos los que esperan',
            self::SCOPE_ZOMBIES => 'Los colgados (el worker que los tomó ya no responde)',
            self::SCOPE_ALL => 'Todo lo de la cola (incluye programados y colgados)',
        ];
    }

    public static function isSupported(): bool
    {
        return self::store() !== null;
    }

    /**
     * Para pruebas: fuerza un acceso a la cola, o con $resolved = false vuelve a resolverlo.
     */
    public static function swapStore(?QueueJobStore $store, bool $resolved = true): void
    {
        self::$store = $store;
        self::$storeResolved = $resolved;
    }

    public static function store(): ?QueueJobStore
    {
        if (self::$storeResolved) {
            return self::$store;
        }

        try {
            $connection = Queue::connection();
        } catch (Throwable) {
            return null;
        }

        if ($connection instanceof DatabaseQueue) {
            return new DatabaseQueueJobStore(
                $connection->getDatabase(),
                (string) config('queue.connections.'.config('queue.default').'.table', 'jobs'),
            );
        }

        if ($connection instanceof RedisQueue) {
            return new RedisQueueJobStore($connection->getConnection(), static fn (string $queue): string => $connection->getQueue($queue));
        }

        return null;
    }

    /**
     * Cuántos trabajos tocaría la operación, por alcance.
     *
     * @return array<string, int>
     */
    public static function counts(string $queue, ?string $jobClass = null): array
    {
        $entries = self::entries($queue, true);
        $counts = [];

        foreach (array_keys(self::scopeLabels()) as $scope) {
            $counts[$scope] = count(self::select($entries, $scope, $jobClass));
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

        foreach (self::entries($queue) as $entry) {
            $class = FailedJob::jobClassFromPayload((string) $entry['payload']);
            $classes[$class] = ($classes[$class] ?? 0) + 1;
        }

        arsort($classes);
        $options = [];

        foreach ($classes as $class => $count) {
            $options[$class] = $count.' × '.class_basename($class);
        }

        return $options;
    }

    /**
     * Qué tipo de trabajo espera y cuántos están colgados, para el tablero.
     *
     * @return array{pending_by_class: array<string, int>, zombies: int}
     */
    public static function summary(string $queue): array
    {
        $entries = self::entries($queue);
        $byClass = [];

        foreach ($entries as $entry) {
            if ($entry['bucket'] === 'pending') {
                $job = class_basename(FailedJob::jobClassFromPayload((string) $entry['payload']));
                $byClass[$job] = ($byClass[$job] ?? 0) + 1;
            }
        }

        arsort($byClass);

        return ['pending_by_class' => $byClass, 'zombies' => count(self::select($entries, self::SCOPE_ZOMBIES, null))];
    }

    /**
     * @return array{affected: int, moved: int, deleted: int}
     *
     * @throws InvalidArgumentException
     */
    public static function release(string $queue, string $scope, string $mode, ?string $jobClass = null, string $reason = '', ?string $actor = null): array
    {
        $store = self::store();

        if ($store === null) {
            throw new InvalidArgumentException('Liberar colas solo está disponible con los drivers de colas «database» o «redis».');
        }

        if (! array_key_exists($scope, self::scopeLabels()) || ! in_array($mode, [self::MODE_MOVE, self::MODE_DELETE], true)) {
            throw new InvalidArgumentException('La operación no es válida.');
        }

        $queue = trim($queue);

        if ($queue === '') {
            throw new InvalidArgumentException('Indique la cola.');
        }

        $actor ??= 'system';
        $selected = self::select(self::entries($queue, true), $scope, $jobClass === '' ? null : $jobClass);
        $message = 'App\Support\LivePresence\QueueJobActions: Sacado de la cola a mano desde el monitor por '.$actor.'.'
            .($reason !== '' ? ' Motivo: '.Str::limit(trim($reason), 500) : '')
            .' in '.__FILE__.':'.__LINE__;

        $archive = $mode === self::MODE_MOVE
            ? static function (string $payload, string $jobQueue) use ($message): string {
                $uuid = self::uuidFor($payload);

                FailedJob::query()->insert([
                    'uuid' => $uuid,
                    'connection' => (string) config('queue.default'),
                    'queue' => $jobQueue,
                    'payload' => $payload,
                    'exception' => $message,
                    'failed_at' => now(),
                ]);

                return $uuid;
            }
        : null;

        $unarchive = static function (string $uuid): void {
            FailedJob::query()->where('uuid', $uuid)->delete();
        };

        $removed = $store->remove($queue, $selected, $archive, $unarchive);
        $result = ['affected' => $removed, 'moved' => $mode === self::MODE_MOVE ? $removed : 0, 'deleted' => $mode === self::MODE_DELETE ? $removed : 0];

        FailedJobCatalog::forgetCache();

        try {
            SecurityAudit::log('AUDIT_LIVE_QUEUE_RELEASED', 'live-presence.queues', [
                'queue' => $queue,
                'scope' => $scope,
                'mode' => $mode,
                'job_class' => $jobClass,
                'reason' => $reason,
                'driver' => (string) config('queue.default'),
                ...$result,
            ]);
        } catch (Throwable) {
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function entries(string $queue, bool $fullPayload = false): array
    {
        try {
            return self::store()?->entries($queue, $fullPayload) ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private static function select(array $entries, string $scope, ?string $jobClass): array
    {
        $now = now()->getTimestamp();
        $stuckBefore = $now - self::stuckAfterMinutes() * 60;
        $retryAfter = self::retryAfter();

        if ($jobClass !== null) {
            $entries = array_values(array_filter($entries, static fn (array $entry): bool => FailedJob::jobClassFromPayload((string) $entry['payload']) === $jobClass));
        }

        if ($scope === self::SCOPE_DUPLICATES) {
            return self::duplicates($entries);
        }

        return array_values(array_filter($entries, static function (array $entry) use ($scope, $now, $stuckBefore, $retryAfter): bool {
            $since = $entry['available_at'] ?? $entry['created_at'];

            return match ($scope) {
                self::SCOPE_STUCK => $entry['bucket'] === 'pending' && $since !== null && $since <= $stuckBefore,
                self::SCOPE_PENDING => $entry['bucket'] === 'pending',
                /** database: reservado hace más de retry_after. redis: la reserva ya venció. */
                self::SCOPE_ZOMBIES => $entry['bucket'] === 'reserved' && (
                    ($entry['reserved_until'] !== null && $entry['reserved_until'] < $now)
                    || ($entry['reserved_at'] !== null && $entry['reserved_at'] < $now - $retryAfter)
                ),
                default => true,
            };
        }));
    }

    /**
     * Pendientes idénticos (mismo trabajo con los mismos datos): se deja el
     * más reciente de cada uno y se seleccionan los demás. Es el caso típico de
     * un trabajo programado que se acumula día tras día sin worker. Dos
     * WhatsApp a personas distintas no son idénticos y nunca se tocan.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private static function duplicates(array $entries): array
    {
        $groups = [];

        foreach ($entries as $index => $entry) {
            if ($entry['bucket'] !== 'pending') {
                continue;
            }

            $decoded = json_decode((string) $entry['payload'], true);

            if (! is_array($decoded) || ! isset($decoded['data']['command']) || ! is_string($decoded['data']['command'])) {
                continue;
            }

            $identity = sha1(((string) ($decoded['displayName'] ?? '')).'|'.$decoded['data']['command']);
            $groups[$identity][] = ['index' => $index, 'at' => (int) ($entry['created_at'] ?? $entry['available_at'] ?? 0)];
        }

        $selected = [];

        foreach ($groups as $members) {
            if (count($members) < 2) {
                continue;
            }

            usort($members, static fn (array $a, array $b): int => [$b['at'], $b['index']] <=> [$a['at'], $a['index']]);

            foreach (array_slice($members, 1) as $member) {
                $selected[] = $entries[$member['index']];
            }
        }

        return $selected;
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

    private static function stuckAfterMinutes(): int
    {
        return max(1, (int) config('live-presence.queues.stuck_after_minutes', 30));
    }

    private static function retryAfter(): int
    {
        return max(60, (int) config('queue.connections.'.config('queue.default').'.retry_after', 90));
    }
}
