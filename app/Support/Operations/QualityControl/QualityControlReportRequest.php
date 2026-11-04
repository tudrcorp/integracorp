<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Enums\QualityControlAffiliateType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * El informe se pide desde la tabla filtrada y se muestra en un iframe: entre ambos pasos el
 * navegador solo lleva un token. Las encuestas, el tipo y el usuario quedan en caché del
 * servidor, y el token solo vale para quien lo creó.
 */
final class QualityControlReportRequest
{
    private const TTL_MINUTES = 120;

    /**
     * @param  list<int>  $ids
     */
    public static function store(QualityControlAffiliateType $type, array $ids, string $label): string
    {
        $token = (string) Str::uuid();

        Cache::put(self::key($token), [
            'type' => $type->value,
            'ids' => array_values(array_map('intval', $ids)),
            'label' => $label,
            'user_id' => (int) Auth::id(),
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * @return array{type: string, ids: list<int>, label: string, user_id: int}|null
     */
    public static function find(string $token): ?array
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        $pending = Cache::get(self::key($token));

        if (! is_array($pending) || (int) ($pending['user_id'] ?? 0) !== (int) Auth::id()) {
            return null;
        }

        return $pending;
    }

    private static function key(string $token): string
    {
        return 'quality-control-report:'.$token;
    }
}
