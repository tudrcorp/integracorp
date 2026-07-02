<?php

declare(strict_types=1);

namespace App\Support\Companies;

final class CompanyAssociatesGroupColor
{
    public const DEFAULT = '#6366f1';

    /**
     * @var list<string>
     */
    private const PALETTE = [
        '#6366f1',
        '#0ea5e9',
        '#14b8a6',
        '#8b5cf6',
        '#ec4899',
        '#f59e0b',
        '#22c55e',
        '#ef4444',
        '#06b6d4',
        '#a855f7',
    ];

    public static function forResponsibleId(?int $responsibleId): string
    {
        if ($responsibleId === null) {
            return self::DEFAULT;
        }

        $index = abs(crc32((string) $responsibleId)) % count(self::PALETTE);

        return self::PALETTE[$index];
    }

    public static function inlineRowStyle(?string $hexColor): ?string
    {
        if (! self::isHexColor($hexColor)) {
            return null;
        }

        return 'background-color: color-mix(in srgb, '.$hexColor.' 18%, transparent); border-left: 4px solid '.$hexColor;
    }

    public static function inlineGroupHeaderStyle(?string $hexColor): ?string
    {
        if (! self::isHexColor($hexColor)) {
            return null;
        }

        return 'background-color: color-mix(in srgb, '.$hexColor.' 30%, transparent); border-left: 4px solid '.$hexColor;
    }

    private static function isHexColor(?string $hexColor): bool
    {
        return is_string($hexColor) && str_starts_with($hexColor, '#');
    }
}
