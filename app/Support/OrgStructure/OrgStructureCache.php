<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Enums\OrgStructureVersionStatus;
use App\Models\OrgStructureVersion;
use Illuminate\Support\Facades\Cache;

/**
 * Claves de caché del organigrama.
 *
 * El árbol de una versión ocupa una sola entrada, firmada con (lock_version, sello
 * de RRHH): editar el borrador sube lock_version y cualquier cambio visible en RRHH
 * sube el sello; si la firma no coincide se recalcula y se sobrescribe. Una entrada
 * por versión y no una por firma, porque con el driver `database` las claves viejas
 * nunca se vuelven a leer y por eso nunca se borrarían.
 */
final class OrgStructureCache
{
    private const RRHH_STAMP_KEY = 'org-structure:rrhh-stamp';

    private const CURRENT_KEY = 'org-structure:current-version';

    public const PAYLOAD_TTL_SECONDS = 21_600;

    public static function rrhhStamp(): int
    {
        return (int) Cache::get(self::RRHH_STAMP_KEY, 0);
    }

    /**
     * Invalida todos los árboles: un colaborador cambió nombre, foto, cargo o estado.
     */
    public static function bumpRrhhStamp(): void
    {
        Cache::forever(self::RRHH_STAMP_KEY, self::rrhhStamp() + 1);
    }

    public static function payloadKey(OrgStructureVersion $version): string
    {
        return 'org-structure:payload:'.$version->getKey();
    }

    public static function payloadSignature(OrgStructureVersion $version): string
    {
        return $version->lock_version.':'.self::rrhhStamp();
    }

    /**
     * @return array{id: int, lock_version: int}|null
     */
    public static function currentVersionRef(): ?array
    {
        /** @var array{id: int, lock_version: int}|false|null $ref */
        $ref = Cache::remember(self::CURRENT_KEY, 600, function (): array|false {
            $version = OrgStructureVersion::query()
                ->current()
                ->where('status', OrgStructureVersionStatus::Current->value)
                ->first(['id', 'lock_version']);

            return $version === null ? false : ['id' => (int) $version->id, 'lock_version' => (int) $version->lock_version];
        });

        return is_array($ref) ? $ref : null;
    }

    public static function currentVersion(): ?OrgStructureVersion
    {
        $ref = self::currentVersionRef();

        if ($ref === null) {
            return null;
        }

        $version = OrgStructureVersion::query()->find($ref['id']);

        if ($version === null || $version->status !== OrgStructureVersionStatus::Current) {
            self::forgetCurrent();

            return OrgStructureVersion::query()->current()->first();
        }

        return $version;
    }

    public static function forgetCurrent(): void
    {
        Cache::forget(self::CURRENT_KEY);
    }
}
