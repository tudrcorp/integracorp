<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;

final class OperationsMapSearchAddress
{
    public static function compose(?string $street, ?string $location): ?string
    {
        if ($street !== null && $location !== null) {
            return $street.', '.$location;
        }

        return $street ?? $location;
    }

    /**
     * Dirección completa para el buscador del mapa: calle + país · estado · ciudad · región.
     */
    public static function forAffiliate(Affiliate $record): ?string
    {
        if ($record->exists) {
            $record->loadMissing(['country', 'state', 'city']);
        }

        $street = filled($record->address) ? trim((string) $record->address) : null;
        $location = self::locationLine(
            $record->country?->name,
            $record->state?->definition,
            $record->city?->definition,
            filled($record->region) ? (string) $record->region : null,
        );

        return self::compose($street, $location);
    }

    public static function forAffiliateCorporate(AffiliateCorporate $record): ?string
    {
        $street = filled($record->address) ? trim((string) $record->address) : null;

        return self::compose($street, null);
    }

    public static function forAffiliationCorporate(AffiliationCorporate $corporate): ?string
    {
        if ($corporate->exists) {
            $corporate->loadMissing(['country', 'state', 'city', 'region']);
        }

        $street = filled($corporate->address) ? trim((string) $corporate->address) : null;
        $location = self::locationLine(
            $corporate->country?->name,
            $corporate->state?->definition,
            $corporate->city?->definition,
            $corporate->region?->definition,
        );

        return self::compose($street, $location);
    }

    /**
     * Línea de ubicación administrativa (país · estado · ciudad · región).
     */
    public static function locationLine(?string $country, ?string $state, ?string $city, ?string $region): ?string
    {
        $line = collect([$country, $state, $city, $region])
            ->filter(fn (?string $part): bool => filled($part))
            ->unique()
            ->implode(' · ');

        return filled($line) ? $line : null;
    }
}
