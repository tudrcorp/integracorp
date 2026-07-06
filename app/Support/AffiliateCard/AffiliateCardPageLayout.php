<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

/**
 * Posiciones calibradas contra la vista DomPDF `tarjeta-afiliado` (A4, 96 DPI).
 */
final class AffiliateCardPageLayout
{
    public const WIDTH_MM = 210.0;

    public const HEIGHT_MM = 297.0;

    public const CANVAS_WIDTH_PX = 793.7;

    public const PX_TO_MM = 25.4 / 96;

    public const FONT_FAMILY = 'Helvetica';

    public const FONT_STYLE = 'B';

    public const FONT_SIZE_PT = 9;

    /**
     * @var array<string, array{
     *     left_px?: float,
     *     right_px?: float,
     *     top_px: float,
     *     width_mm: float,
     *     align: 'L'|'R'|'C'
     * }>
     */
    private const FIELD_POSITIONS = [
        'code' => ['left_px' => 251, 'top_px' => 335, 'width_mm' => 42, 'align' => 'L'],
        'name_first_part' => ['left_px' => 115, 'top_px' => 354, 'width_mm' => 70, 'align' => 'L'],
        'name_second_part' => ['left_px' => 115, 'top_px' => 368, 'width_mm' => 70, 'align' => 'L'],
        'ci' => ['left_px' => 80, 'top_px' => 402, 'width_mm' => 55, 'align' => 'L'],
        'plan' => ['left_px' => 95, 'top_px' => 426, 'width_mm' => 45, 'align' => 'L'],
        'frecuencia' => ['left_px' => 195, 'top_px' => 450, 'width_mm' => 35, 'align' => 'L'],
        'cobertura' => ['left_px' => 130, 'top_px' => 473.5, 'width_mm' => 40, 'align' => 'L'],
        'desde' => ['left_px' => 440, 'top_px' => 423, 'width_mm' => 28, 'align' => 'L'],
        'hasta' => ['left_px' => 440, 'top_px' => 443, 'width_mm' => 28, 'align' => 'L'],
    ];

    /**
     * @return array{x: float, y: float, width_mm: float, align: 'L'|'R'|'C'}
     */
    public static function fieldPosition(string $field): array
    {
        $definition = self::FIELD_POSITIONS[$field] ?? null;

        if ($definition === null) {
            return ['x' => 0.0, 'y' => 0.0, 'width_mm' => 40.0, 'align' => 'L'];
        }

        $y = self::pxToMm($definition['top_px']);

        if (isset($definition['left_px'])) {
            return [
                'x' => self::pxToMm($definition['left_px']),
                'y' => $y,
                'width_mm' => $definition['width_mm'],
                'align' => $definition['align'],
            ];
        }

        $rightEdgeMm = self::pxToMm(self::CANVAS_WIDTH_PX - $definition['right_px']);

        return [
            'x' => $rightEdgeMm - $definition['width_mm'],
            'y' => $y,
            'width_mm' => $definition['width_mm'],
            'align' => $definition['align'],
        ];
    }

    public static function pxToMm(float $pixels): float
    {
        return round($pixels * self::PX_TO_MM, 2);
    }
}
