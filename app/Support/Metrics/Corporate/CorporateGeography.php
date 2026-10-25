<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Estados de Venezuela: une el catálogo `states` (en mayúsculas y sin tildes)
 * con las formas del mapa (`resources/geo/venezuela-states-paths.php`, con
 * tildes) normalizando el nombre.
 */
final class CorporateGeography
{
    public const UNKNOWN = 'Sin estado';

    /**
     * @var array<string, string>|null nombre normalizado => clave del mapa
     */
    private static ?array $geoKeys = null;

    public static function normalize(?string $name): string
    {
        return Str::upper(Str::ascii(trim((string) $name)));
    }

    /**
     * Clave del mapa para un nombre de estado («ANZOATEGUI» → «Anzoátegui»).
     */
    public static function geoKey(?string $name): ?string
    {
        if (self::$geoKeys === null) {
            self::$geoKeys = [];

            foreach (self::paths() as $path) {
                self::$geoKeys[self::normalize($path['geo_key'])] = $path['geo_key'];
            }
        }

        return self::$geoKeys[self::normalize($name)] ?? null;
    }

    /**
     * @return list<array{geo_key: string, d: string, cx: float, cy: float}>
     */
    public static function paths(): array
    {
        /** @var list<array{geo_key: string, d: string, cx: float, cy: float}> */
        return require resource_path('geo/venezuela-states-paths.php');
    }

    /**
     * Estados del catálogo de Venezuela: id => clave del mapa.
     *
     * @return array<int, string>
     */
    public static function stateIds(): array
    {
        return CorporateMetrics::remember('geo_state_ids', null, static function (): array {
            $out = [];

            foreach (DB::table('states')->get(['id', 'definition']) as $state) {
                $key = self::geoKey((string) $state->definition);

                if ($key !== null) {
                    $out[(int) $state->id] = $key;
                }
            }

            return $out;
        });
    }

    /**
     * Suma por clave del mapa a partir de filas id de estado => valor.
     *
     * @param  iterable<int|string, float|int>  $byStateId
     * @return array<string, float>
     */
    public static function byGeoKeyFromIds(iterable $byStateId): array
    {
        $ids = self::stateIds();
        $out = [];

        foreach ($byStateId as $stateId => $value) {
            $key = $ids[(int) $stateId] ?? self::UNKNOWN;
            $out[$key] = round(($out[$key] ?? 0) + (float) $value, 2);
        }

        return $out;
    }

    /**
     * Suma por clave del mapa a partir de filas nombre de estado => valor.
     *
     * @param  iterable<string, float|int>  $byStateName
     * @return array<string, float>
     */
    public static function byGeoKeyFromNames(iterable $byStateName): array
    {
        $out = [];

        foreach ($byStateName as $name => $value) {
            $key = self::geoKey((string) $name) ?? self::UNKNOWN;
            $out[$key] = round(($out[$key] ?? 0) + (float) $value, 2);
        }

        return $out;
    }
}
