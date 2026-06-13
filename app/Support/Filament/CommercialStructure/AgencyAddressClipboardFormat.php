<?php

declare(strict_types=1);

namespace App\Support\Filament\CommercialStructure;

use App\Models\Agency;
use App\Models\Country;
use App\Support\CountrySelectOptions;

final class AgencyAddressClipboardFormat
{
    public static function canCopyVenezuela(Agency $record): bool
    {
        return filled($record->name_representative)
            || filled($record->name_corporative)
            || filled($record->address)
            || filled($record->city?->definition)
            || filled($record->state?->definition)
            || filled($record->country?->name);
    }

    public static function canCopyInternational(Agency $record): bool
    {
        return filled($record->name_representative)
            || filled($record->name_corporative)
            || filled($record->address_other_country)
            || filled($record->city_other_country)
            || filled($record->state_other_country)
            || filled($record->postal_code_other_country)
            || filled(self::otherCountryName($record->country_other_country));
    }

    public static function venezuela(Agency $record): string
    {
        $lines = self::headerLines($record);

        if (filled($record->address)) {
            $lines[] = 'Dirección: '.trim((string) $record->address);
        }

        $city = $record->city?->definition;
        $state = $record->state?->definition;

        if (filled($city) || filled($state)) {
            $locationLine = 'Ciudad: ';

            if (filled($city)) {
                $locationLine .= trim((string) $city);
            }

            if (filled($state)) {
                $locationLine .= filled($city) ? ', ' : '';
                $locationLine .= 'Estado: '.trim((string) $state);
            }

            $lines[] = $locationLine;
        }

        $country = filled($record->country?->name)
            ? trim((string) $record->country->name)
            : 'Venezuela';

        $lines[] = 'País: '.$country;

        return implode("\n", $lines);
    }

    public static function international(Agency $record): string
    {
        $lines = self::headerLines($record);

        if (filled($record->address_other_country)) {
            $lines[] = 'Dirección: '.trim((string) $record->address_other_country);
        }

        $city = $record->city_other_country;
        $state = trim(implode(' ', array_values(array_filter([
            filled($record->state_other_country) ? trim((string) $record->state_other_country) : null,
            filled($record->postal_code_other_country) ? trim((string) $record->postal_code_other_country) : null,
        ]))));

        if (filled($city) || filled($state)) {
            $locationLine = 'Ciudad: ';

            if (filled($city)) {
                $locationLine .= trim((string) $city);
            }

            if (filled($state)) {
                $locationLine .= filled($city) ? ', ' : '';
                $locationLine .= 'Estado: '.$state;
            }

            $lines[] = $locationLine;
        }

        $country = self::otherCountryName($record->country_other_country);

        if (filled($country)) {
            $lines[] = 'País: '.$country;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function headerLines(Agency $record): array
    {
        $lines = [];

        if (filled($record->name_representative)) {
            $lines[] = trim((string) $record->name_representative);
        }

        if (filled($record->name_corporative)) {
            $lines[] = 'Agencia: '.trim((string) $record->name_corporative);
        }

        return $lines;
    }

    private static function otherCountryName(mixed $countryId): ?string
    {
        if (blank($countryId)) {
            return null;
        }

        return CountrySelectOptions::exceptVenezuelaInSpanish()[(int) $countryId]
            ?? Country::query()->whereKey($countryId)->value('name');
    }
}
