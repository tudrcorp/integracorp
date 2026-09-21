<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

final class CorporateAffiliateRelationship
{
    public const DEFAULT = 'COLABORADOR';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'COLABORADOR' => 'Colaborador',
            'TITULAR' => 'Titular',
            'MADRE' => 'Madre',
            'PADRE' => 'Padre',
            'ESPOSA' => 'Esposa',
            'ESPOSO' => 'Esposo',
            'HIJO' => 'Hijo',
            'HIJA' => 'Hija',
            'OTRO' => 'Otro',
        ];
    }

    public static function label(?string $value): ?string
    {
        $key = strtoupper(trim((string) $value));

        if ($key === '') {
            return null;
        }

        return self::options()[$key] ?? $value;
    }

    public static function forCertificate(?string $value): string
    {
        $key = strtoupper(trim((string) $value));

        if ($key === '' || ! array_key_exists($key, self::options())) {
            return self::DEFAULT;
        }

        return $key;
    }
}
