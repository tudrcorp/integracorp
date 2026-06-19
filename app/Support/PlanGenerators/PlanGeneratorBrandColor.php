<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

final class PlanGeneratorBrandColor
{
    public const DEFAULT = '#1d4ed8';

    public static function resolve(?string $color): string
    {
        $normalized = strtoupper(ltrim(trim((string) $color), '#'));

        if (preg_match('/^[0-9A-F]{6}$/', $normalized) !== 1) {
            return self::DEFAULT;
        }

        return '#'.strtolower($normalized);
    }

    public static function headerBorderColor(?string $color): string
    {
        return self::adjustBrightness(self::resolve($color), -0.12);
    }

    private static function adjustBrightness(string $hexColor, float $factor): string
    {
        $hex = ltrim(self::resolve($hexColor), '#');

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        if ($factor < 0) {
            $red = (int) max(0, min(255, round($red * (1 + $factor))));
            $green = (int) max(0, min(255, round($green * (1 + $factor))));
            $blue = (int) max(0, min(255, round($blue * (1 + $factor))));
        } else {
            $red = (int) max(0, min(255, round($red + ((255 - $red) * $factor))));
            $green = (int) max(0, min(255, round($green + ((255 - $green) * $factor))));
            $blue = (int) max(0, min(255, round($blue + ((255 - $blue) * $factor))));
        }

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }
}
