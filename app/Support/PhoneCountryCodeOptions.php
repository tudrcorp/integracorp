<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneCountryCodeOptions
{
    /**
     * Prefijos telefónicos más usados en registro de agencias (Américas, España, EE. UU. y otros frecuentes).
     *
     * @return array<string, string>
     */
    public static function common(): array
    {
        return [
            '+58' => '🇻🇪 +58',
            '+57' => '🇨🇴 +57',
            '+51' => '🇵🇪 +51',
            '+56' => '🇨🇱 +56',
            '+54' => '🇦🇷 +54',
            '+593' => '🇪🇨 +593',
            '+591' => '🇧🇴 +591',
            '+598' => '🇺🇾 +598',
            '+595' => '🇵🇾 +595',
            '+507' => '🇵🇦 +507',
            '+506' => '🇨🇷 +506',
            '+502' => '🇬🇹 +502',
            '+503' => '🇸🇻 +503',
            '+504' => '🇭🇳 +504',
            '+505' => '🇳🇮 +505',
            '+52' => '🇲🇽 +52',
            '+1' => '🇺🇸 +1',
            '+55' => '🇧🇷 +55',
            '+34' => '🇪🇸 +34',
            '+39' => '🇮🇹 +39',
            '+33' => '🇫🇷 +33',
            '+49' => '🇩🇪 +49',
            '+44' => '🇬🇧 +44',
            '+351' => '🇵🇹 +351',
            '+31' => '🇳🇱 +31',
            '+41' => '🇨🇭 +41',
            '+971' => '🇦🇪 +971',
        ];
    }

    public static function isAllowed(string $countryCode): bool
    {
        return array_key_exists($countryCode, self::common());
    }

    public static function isVenezuela(string $countryCode): bool
    {
        return $countryCode === '+58';
    }
}
