<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Un borrador lo edita una sola persona a la vez.
 *
 * El candado vive en caché con un vencimiento corto y la pantalla lo renueva con un
 * latido. Si el editor cierra la pestaña o se cae la conexión, el candado expira
 * solo y otro analista puede tomar el borrador. Un SUPERADMIN puede tomar el
 * control antes, con confirmación.
 */
final class OrgStructureEditLock
{
    public const TTL_SECONDS = 120;

    public const HEARTBEAT_SECONDS = 40;

    /**
     * @return array{user_id: int, name: string, at: string}|null
     */
    public static function holder(int $versionId): ?array
    {
        $value = Cache::get(self::key($versionId));

        return is_array($value) && isset($value['user_id']) ? $value : null;
    }

    /**
     * Toma o renueva el candado. Devuelve null si quedó para este usuario, o quién lo tiene.
     *
     * @return array{user_id: int, name: string, at: string}|null
     */
    public static function acquire(int $versionId, User $user): ?array
    {
        $payload = self::payloadFor($user);
        $holder = self::holder($versionId);

        if ($holder === null) {
            if (Cache::add(self::key($versionId), $payload, self::TTL_SECONDS)) {
                return null;
            }

            $holder = self::holder($versionId);

            if ($holder === null) {
                Cache::put(self::key($versionId), $payload, self::TTL_SECONDS);

                return null;
            }
        }

        if ((int) $holder['user_id'] === (int) $user->getKey()) {
            Cache::put(self::key($versionId), [...$payload, 'at' => $holder['at']], self::TTL_SECONDS);

            return null;
        }

        return $holder;
    }

    /**
     * Exige el candado para escribir; si lo tiene otro, la escritura se rechaza.
     */
    public static function ensure(int $versionId, User $user): void
    {
        $holder = self::acquire($versionId, $user);

        if ($holder !== null) {
            throw new OrgStructureException('Este borrador lo está editando '.$holder['name'].'. Podrás editarlo cuando lo cierre.');
        }
    }

    public static function release(int $versionId, User $user): void
    {
        $holder = self::holder($versionId);

        if ($holder !== null && (int) $holder['user_id'] === (int) $user->getKey()) {
            Cache::forget(self::key($versionId));
        }
    }

    public static function forceTakeover(int $versionId, User $user): void
    {
        Cache::put(self::key($versionId), self::payloadFor($user), self::TTL_SECONDS);
    }

    public static function clear(int $versionId): void
    {
        Cache::forget(self::key($versionId));
    }

    /**
     * @return array{user_id: int, name: string, at: string}
     */
    private static function payloadFor(User $user): array
    {
        return [
            'user_id' => (int) $user->getKey(),
            'name' => (string) ($user->name ?? 'Otro usuario'),
            'at' => now()->toIso8601String(),
        ];
    }

    private static function key(int $versionId): string
    {
        return 'org-structure:edit-lock:'.$versionId;
    }
}
