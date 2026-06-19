<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

final class PlanGeneratorMatrixColumnLayout
{
    /** Ancho útil del cuerpo en PDF (A4 210mm − márgenes 20mm × 2). */
    public const PDF_CONTENT_WIDTH_MM = 170.0;

    public const LEAD_PERCENT = 32.0;

    public const RATE_AGE_PERCENT = 22.0;

    public const RATE_POP_PERCENT = 10.0;

    public const PLAN_BLOCK_PERCENT = 68.0;

    public static function planColumnCount(array $columns): int
    {
        return max(1, count($columns));
    }

    public static function planColumnPercent(int $columnCount): float
    {
        return self::PLAN_BLOCK_PERCENT / max(1, $columnCount);
    }

    public static function leadWidthMm(): string
    {
        return self::formatMm(self::PDF_CONTENT_WIDTH_MM * self::LEAD_PERCENT / 100);
    }

    public static function rateAgeWidthMm(): string
    {
        return self::formatMm(self::PDF_CONTENT_WIDTH_MM * self::RATE_AGE_PERCENT / 100);
    }

    public static function ratePopWidthMm(): string
    {
        return self::formatMm(self::PDF_CONTENT_WIDTH_MM * self::RATE_POP_PERCENT / 100);
    }

    public static function planColumnWidthMm(int $columnCount): string
    {
        $planBlockMm = self::PDF_CONTENT_WIDTH_MM * self::PLAN_BLOCK_PERCENT / 100;

        return self::formatMm($planBlockMm / max(1, $columnCount));
    }

    private static function formatMm(float $millimeters): string
    {
        return number_format($millimeters, 2, '.', '');
    }
}
