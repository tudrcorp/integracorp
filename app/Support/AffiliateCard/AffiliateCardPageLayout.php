<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

/**
 * Posiciones calibradas para estampado FPDI (A4 @ 96 DPI).
 */
final class AffiliateCardPageLayout
{
    public const TEMPLATE_INCLUSION = 'inclusion';

    public const TEMPLATE_INDIVIDUAL = 'individual';

    public const WIDTH_MM = 210.0;

    public const HEIGHT_MM = 297.0;

    public const CANVAS_WIDTH_PX = 793.7;

    public const PX_TO_MM = 25.4 / 96;

    public const FONT_FAMILY = 'Helvetica';

    public const FONT_STYLE = 'B';

    public const FONT_SIZE_PT = 9;

    /**
     * @var array<string, array<string, array{
     *     left_px?: float,
     *     right_px?: float,
     *     top_px: float,
     *     width_mm: float,
     *     align: 'L'|'R'|'C'
     * }>>
     */
    private const FIELD_POSITIONS_BY_TEMPLATE = [
        self::TEMPLATE_INCLUSION => [
            'code' => ['left_px' => 392, 'top_px' => 354, 'width_mm' => 38, 'align' => 'L'],
            'name_first_part' => ['left_px' => 252, 'top_px' => 422, 'width_mm' => 70, 'align' => 'L'],
            'name_second_part' => ['left_px' => 252, 'top_px' => 436, 'width_mm' => 70, 'align' => 'L'],
            'ci' => ['left_px' => 112, 'top_px' => 448, 'width_mm' => 55, 'align' => 'L'],
            'plan' => ['left_px' => 122, 'top_px' => 468, 'width_mm' => 45, 'align' => 'L'],
            'frecuencia' => ['left_px' => 248, 'top_px' => 488, 'width_mm' => 35, 'align' => 'L'],
            'cobertura' => ['left_px' => 172, 'top_px' => 492, 'width_mm' => 40, 'align' => 'L'],
            'desde' => ['left_px' => 452, 'top_px' => 454, 'width_mm' => 28, 'align' => 'L'],
            'hasta' => ['left_px' => 452, 'top_px' => 469, 'width_mm' => 28, 'align' => 'L'],
        ],
        self::TEMPLATE_INDIVIDUAL => [
            'code' => ['left_px' => 267, 'top_px' => 370, 'width_mm' => 38, 'align' => 'L'],
            'name_first_part' => ['left_px' => 118, 'top_px' => 406, 'width_mm' => 70, 'align' => 'L'],
            'name_second_part' => ['left_px' => 118, 'top_px' => 420, 'width_mm' => 70, 'align' => 'L'],
            'ci' => ['left_px' => 88, 'top_px' => 440, 'width_mm' => 55, 'align' => 'L'],
            'plan' => ['left_px' => 98, 'top_px' => 468, 'width_mm' => 45, 'align' => 'L'],
            'frecuencia' => ['left_px' => 203, 'top_px' => 494, 'width_mm' => 35, 'align' => 'L'],
            'cobertura' => ['left_px' => 138, 'top_px' => 520, 'width_mm' => 40, 'align' => 'L'],
            'desde' => ['left_px' => 455, 'top_px' => 464, 'width_mm' => 28, 'align' => 'L'],
            'hasta' => ['left_px' => 455, 'top_px' => 485, 'width_mm' => 28, 'align' => 'L'],
        ],
    ];

    /**
     * @var array<string, array{top_px: float, right_px: float, size_px: float}>
     */
    private const QR_POSITIONS_BY_TEMPLATE = [
        self::TEMPLATE_INDIVIDUAL => [
            'top_px' => 425,
            'right_px' => 135,
            'size_px' => 80,
        ],
    ];

    /**
     * @return array{x: float, y: float, width_mm: float, align: 'L'|'R'|'C'}
     */
    public static function fieldPosition(string $field, string $templateKey = self::TEMPLATE_INCLUSION): array
    {
        $definition = self::FIELD_POSITIONS_BY_TEMPLATE[$templateKey][$field] ?? null;

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

    /**
     * @return array{x_mm: float, y_mm: float, size_mm: float}|null
     */
    public static function qrPosition(string $templateKey): ?array
    {
        $definition = self::QR_POSITIONS_BY_TEMPLATE[$templateKey] ?? null;

        if ($definition === null) {
            return null;
        }

        $sizeMm = self::pxToMm($definition['size_px']);
        $xMm = self::pxToMm(self::CANVAS_WIDTH_PX - $definition['right_px'] - $definition['size_px']);
        $yMm = self::pxToMm($definition['top_px']);

        return [
            'x_mm' => $xMm,
            'y_mm' => $yMm,
            'size_mm' => $sizeMm,
        ];
    }

    public static function pxToMm(float $pixels): float
    {
        return round($pixels * self::PX_TO_MM, 2);
    }
}
