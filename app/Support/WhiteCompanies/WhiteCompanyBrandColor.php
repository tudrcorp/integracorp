<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

final class WhiteCompanyBrandColor
{
    public const DEFAULT = '#26b2ca';

    public static function resolve(?string $color): string
    {
        $normalized = strtolower(ltrim(trim((string) $color), '#'));

        if (preg_match('/^[0-9a-f]{6}$/', $normalized) !== 1) {
            return self::DEFAULT;
        }

        return '#'.$normalized;
    }
}
