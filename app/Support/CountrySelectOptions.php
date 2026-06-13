<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Country;
use ResourceBundle;

final class CountrySelectOptions
{
    private const VENEZUELA_COUNTRY_ID = 183;

    /**
     * Nombres en inglés de la BD que no coinciden con ICU y su clave en inglés estándar.
     *
     * @var array<string, string>
     */
    private const DATABASE_NAME_ALIASES = [
        'ANTIGUA AND BARBUDA' => 'ANTIGUA & BARBUDA',
        'BOSNIA AND HERZEGOVINA' => 'BOSNIA & HERZEGOVINA',
        'CABO VERDE' => 'CAPE VERDE',
        'CONGO (CONGO-BRAZZAVILLE)' => 'CONGO - BRAZZAVILLE',
        'CZECHIA (CZECH REPUBLIC)' => 'CZECHIA',
        'DEMOCRATIC REPUBLIC OF THE CONGO' => 'CONGO - KINSHASA',
        'ESWATINI (FMR. "SWAZILAND")' => 'ESWATINI',
        'HOLY SEE' => 'VATICAN CITY',
        'MYANMAR (FORMERLY BURMA)' => 'MYANMAR (BURMA)',
        'PALESTINE STATE' => 'PALESTINIAN TERRITORIES',
        'SAINT KITTS AND NEVIS' => 'ST. KITTS & NEVIS',
        'SAINT LUCIA' => 'ST. LUCIA',
        'SAINT VINCENT AND THE GRENADINES' => 'ST. VINCENT & GRENADINES',
        'SAO TOME AND PRINCIPE' => 'SÃO TOMÉ & PRÍNCIPE',
        'TRINIDAD AND TOBAGO' => 'TRINIDAD & TOBAGO',
        'TURKEY' => 'TÜRKIYE',
        'UNITED STATES OF AMERICA' => 'UNITED STATES',
    ];

    /**
     * @return array<int, string>
     */
    public static function exceptVenezuelaInSpanish(): array
    {
        $spanishByEnglishName = self::spanishNamesByEnglishName();

        $options = Country::query()
            ->whereKeyNot(self::VENEZUELA_COUNTRY_ID)
            ->get()
            ->mapWithKeys(function (Country $country) use ($spanishByEnglishName): array {
                return [
                    $country->id => self::spanishNameForDatabaseCountry($country->name, $spanishByEnglishName),
                ];
            })
            ->all();

        natcasesort($options);

        return $options;
    }

    public static function spanishNameForDatabaseCountry(string $databaseCountryName, ?array $spanishByEnglishName = null): string
    {
        $spanishByEnglishName ??= self::spanishNamesByEnglishName();
        $englishKey = self::DATABASE_NAME_ALIASES[$databaseCountryName] ?? $databaseCountryName;

        return $spanishByEnglishName[$englishKey]
            ?? mb_convert_case($databaseCountryName, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    private static function spanishNamesByEnglishName(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $englishCountries = ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries');
        $spanishCountries = ResourceBundle::create('es', 'ICUDATA-region')?->get('Countries');

        if (! $englishCountries instanceof ResourceBundle || ! $spanishCountries instanceof ResourceBundle) {
            return $cache = [];
        }

        $cache = [];

        foreach ($englishCountries as $alpha2 => $englishName) {
            $spanishName = (string) $spanishCountries->get($alpha2);

            if ($spanishName === '') {
                continue;
            }

            $cache[mb_strtoupper((string) $englishName)] = $spanishName;
        }

        return $cache;
    }
}
