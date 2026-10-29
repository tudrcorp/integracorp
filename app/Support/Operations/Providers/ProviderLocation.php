<?php

declare(strict_types=1);

namespace App\Support\Operations\Providers;

use App\Support\Metrics\Corporate\CorporateGeography;
use Illuminate\Support\Facades\DB;

/**
 * Dónde está cada proveedor, en las claves del mapa de Venezuela (`resources/geo`).
 *
 * Los jurídicos guardan el estado por id; los naturales como texto libre («MERIDA », « NUEVA
 * ESPARTA», «MARACAIBO»), que se normaliza aquí sin tocar los datos; los aliados guardan el país y,
 * a veces, el estado. Lo que no se reconoce queda como «sin ubicación».
 */
final class ProviderLocation
{
    public const VENEZUELA_ID = 189;

    /**
     * Ciudades o nombres viejos que aparecen como estado.
     *
     * @var array<string, string>
     */
    private const STATE_ALIASES = [
        'MARACAIBO' => 'Zulia',
        'VARGAS' => 'La Guaira',
        'CARACAS' => 'Distrito Capital',
        'DTTO CAPITAL' => 'Distrito Capital',
        'DISTRITO FEDERAL' => 'Distrito Capital',
        'BARQUISIMETO' => 'Lara',
        'VALENCIA' => 'Carabobo',
        'MARACAY' => 'Aragua',
        'MARGARITA' => 'Nueva Esparta',
        'PUERTO ORDAZ' => 'Bolívar',
    ];

    /**
     * Nombres en español de los países que aparecen en inglés en el catálogo.
     *
     * @var array<string, string>
     */
    private const COUNTRY_NAMES = [
        'UNITED STATES OF AMERICA' => 'Estados Unidos',
        'UNITED STATES' => 'Estados Unidos',
        'SPAIN' => 'España',
        'COLOMBIA' => 'Colombia',
        'PANAMA' => 'Panamá',
        'MEXICO' => 'México',
        'BRAZIL' => 'Brasil',
        'PERU' => 'Perú',
        'DOMINICAN REPUBLIC' => 'República Dominicana',
    ];

    /**
     * @var array<int, string>|null
     */
    private static ?array $countries = null;

    public static function fromStateId(mixed $stateId): ?string
    {
        $id = (int) $stateId;

        return $id > 0 ? (CorporateGeography::stateIds()[$id] ?? null) : null;
    }

    public static function fromStateName(mixed $name): ?string
    {
        $normalized = ProviderNetworkCatalog::normalize($name);

        if ($normalized === '') {
            return null;
        }

        return CorporateGeography::geoKey(self::STATE_ALIASES[$normalized] ?? $normalized);
    }

    public static function countryName(mixed $countryId): string
    {
        if (self::$countries === null) {
            self::$countries = DB::table('countries')->pluck('name', 'id')->map(static fn ($name): string => (string) $name)->all();
        }

        $name = self::$countries[(int) $countryId] ?? '';

        return self::COUNTRY_NAMES[ProviderNetworkCatalog::normalize($name)] ?? ProviderNetworkCatalog::label($name, 'País sin registrar');
    }
}
