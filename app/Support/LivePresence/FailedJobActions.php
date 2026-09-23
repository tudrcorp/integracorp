<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\FailedJob;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Throwable;

/**
 * Gestión de los trabajos fallidos: reintentar y eliminar.
 *
 * Todo va por partes (chunks) para no bloquear la tabla ni agotar la memoria,
 * cada operación queda en la auditoría con cuántos se tocaron, y al final se
 * limpia la caché para que el monitor lo refleje en el siguiente refresco.
 *
 * Reintentar usa `queue:retry` de Laravel: devuelve el trabajo a su conexión y
 * cola originales con los intentos en cero y borra el registro de fallidos.
 */
final class FailedJobActions
{
    private const CHUNK = 200;

    /**
     * @param  list<string>  $uuids
     */
    public static function delete(array $uuids, string $context = 'selección'): int
    {
        $uuids = self::cleanUuids($uuids);
        $deleted = 0;

        foreach (array_chunk($uuids, self::CHUNK) as $chunk) {
            $deleted += FailedJob::query()->whereIn('uuid', $chunk)->delete();
        }

        self::finish('AUDIT_LIVE_QUEUE_FAILED_DELETED', ['context' => $context, 'requested' => count($uuids), 'deleted' => $deleted]);

        return $deleted;
    }

    public static function deleteGroup(string $fingerprint): int
    {
        $group = FailedJobCatalog::group($fingerprint);

        return self::delete(FailedJobCatalog::uuidsForGroup($fingerprint), 'grupo '.($group['job'] ?? '').' · '.($group['diagnosis']['title'] ?? $fingerprint));
    }

    /**
     * Fallidos con más de $days días.
     */
    public static function deleteOlderThan(int $days): int
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Indique al menos 1 día.');
        }

        $deleted = 0;
        $cutoff = now()->subDays($days);

        do {
            $ids = FailedJob::query()->where('failed_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id')->all();

            if ($ids !== []) {
                $deleted += FailedJob::query()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === 1000);

        self::finish('AUDIT_LIVE_QUEUE_FAILED_DELETED', ['context' => 'más de '.$days.' días', 'deleted' => $deleted]);

        return $deleted;
    }

    public static function deleteAll(): int
    {
        $deleted = 0;

        do {
            $ids = FailedJob::query()->orderBy('id')->limit(1000)->pluck('id')->all();

            if ($ids !== []) {
                $deleted += FailedJob::query()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === 1000);

        self::finish('AUDIT_LIVE_QUEUE_FAILED_DELETED', ['context' => 'todos', 'deleted' => $deleted]);

        return $deleted;
    }

    /**
     * @param  list<string>  $uuids
     * @return array{requested: int, retried: int, pending: int}
     */
    public static function retry(array $uuids, string $context = 'selección'): array
    {
        $uuids = self::cleanUuids($uuids);

        foreach (array_chunk($uuids, self::CHUNK) as $chunk) {
            try {
                Artisan::call('queue:retry', ['id' => $chunk]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        /** Lo que no se pudo devolver a la cola sigue en la tabla: se informa, no se oculta. */
        $pending = 0;

        foreach (array_chunk($uuids, 1000) as $chunk) {
            $pending += FailedJob::query()->whereIn('uuid', $chunk)->count();
        }

        $result = ['requested' => count($uuids), 'retried' => count($uuids) - $pending, 'pending' => $pending];

        self::finish('AUDIT_LIVE_QUEUE_FAILED_RETRIED', ['context' => $context, ...$result]);

        return $result;
    }

    /**
     * @return array{requested: int, retried: int, pending: int}
     */
    public static function retryGroup(string $fingerprint): array
    {
        $group = FailedJobCatalog::group($fingerprint);

        return self::retry(FailedJobCatalog::uuidsForGroup($fingerprint), 'grupo '.($group['job'] ?? '').' · '.($group['diagnosis']['title'] ?? $fingerprint));
    }

    /**
     * @param  list<mixed>  $uuids
     * @return list<string>
     */
    private static function cleanUuids(array $uuids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $uuid): string => trim((string) $uuid), $uuids),
            static fn (string $uuid): bool => preg_match('/^[A-Za-z0-9-]{8,64}$/', $uuid) === 1,
        )));
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function finish(string $action, array $details): void
    {
        FailedJobCatalog::forgetCache();

        try {
            SecurityAudit::log($action, 'live-presence.failed-jobs', $details);
        } catch (Throwable) {
        }
    }
}
