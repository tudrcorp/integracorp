<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Support\Affiliations\AffiliationServiceProvidersField;
use Illuminate\Support\Str;

/**
 * Líneas «Unidad de Negocio Específica» y «Proveedor(es) de Servicios» de la búsqueda global de afiliados.
 */
final class GlobalSearchAffiliateBusinessDetails
{
    public const EMPTY = '—';

    /**
     * La del afiliado; si no la tiene (no siempre se sincronizó), la de su afiliación.
     */
    public static function specificBusinessUnit(mixed $affiliateValue, mixed $affiliationValue = null): string
    {
        foreach ([$affiliateValue, $affiliationValue] as $value) {
            if (is_string($value) || is_numeric($value)) {
                $normalized = Str::squish((string) $value);

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return self::EMPTY;
    }

    public static function serviceProviders(mixed $serviceProviders): string
    {
        $names = AffiliationServiceProvidersField::normalizeList($serviceProviders);

        return $names === [] ? self::EMPTY : implode(', ', $names);
    }
}
