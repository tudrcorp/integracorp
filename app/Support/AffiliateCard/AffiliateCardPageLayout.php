<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

/**
 * Posiciones calibradas para estampado FPDI.
 */
final class AffiliateCardPageLayout
{
    public const TEMPLATE_INCLUSION = 'inclusion';

    public const TEMPLATE_INDIVIDUAL = 'individual';

    public const TEMPLATE_INDIVIDUAL_AFFILIATION = 'individual-affiliation';

    public const WIDTH_MM = 210.0;

    public const HEIGHT_MM = 297.0;

    public const CANVAS_WIDTH_PX = 793.7;

    public const INDIVIDUAL_AFFILIATION_SOURCE_WIDTH_PX = 1880.0;

    public const INDIVIDUAL_AFFILIATION_SOURCE_HEIGHT_PX = 672.0;

    /** Carnet en hoja con varios afiliados (cuadrícula 2×4, 8 por página). */
    public const INDIVIDUAL_AFFILIATION_SHEET_UNIT_WIDTH_MM = 93.0;

    public const INDIVIDUAL_AFFILIATION_SHEET_UNIT_HEIGHT_MM = 33.25;

    public const INDIVIDUAL_AFFILIATION_SHEET_CANVAS_WIDTH_PX = 351.0;

    public const INDIVIDUAL_AFFILIATION_SHEET_CANVAS_HEIGHT_PX = 125.0;

    /** Carnet único centrado en hoja vertical. */
    public const INDIVIDUAL_AFFILIATION_SINGLE_UNIT_WIDTH_MM = 165.0;

    public const INDIVIDUAL_AFFILIATION_SINGLE_UNIT_HEIGHT_MM = 59.0;

    public const SHEET_GRID_GAP_MM = 4.0;

    public const SHEET_COLUMNS = 2;

    public const SHEET_ROWS = 4;

    public const CARDS_PER_SHEET = 8;

    public const PX_TO_MM = 25.4 / 96;

    public const FONT_FAMILY = 'Helvetica';

    public const FONT_STYLE = 'B';

    public const FONT_SIZE_PT = 9;

    public const INDIVIDUAL_AFFILIATION_SHEET_FONT_SIZE_PT = 6.5;

    public const INDIVIDUAL_AFFILIATION_SINGLE_FONT_SIZE_PT = 10.5;

    public const INDIVIDUAL_AFFILIATION_SINGLE_CODE_FONT_SIZE_PT = 9.0;

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
        self::TEMPLATE_INDIVIDUAL_AFFILIATION => [
            'code' => ['left_px' => 100, 'left_px_sheet' => 85, 'top_px' => 36, 'width_mm' => 24, 'align' => 'L'],
            'name_first_part' => ['left_px' => 25, 'top_px' => 49, 'left_px_sheet' => 26, 'top_px_sheet' => 49, 'width_mm' => 55, 'align' => 'L'],
            'name_second_part' => ['left_px' => 25, 'top_px' => 57, 'left_px_sheet' => 26, 'top_px_sheet' => 57, 'width_mm' => 55, 'align' => 'L'],
            'ci' => ['left_px' => 40, 'top_px' => 71.5, 'left_px_sheet' => 38, 'top_px_sheet' => 72, 'width_mm' => 28, 'align' => 'L'],
            'plan' => ['left_px' => 46, 'top_px' => 86.5, 'left_px_sheet' => 45, 'top_px_sheet' => 86, 'width_mm' => 28, 'align' => 'L'],
            'frecuencia' => ['left_px' => 93, 'top_px' => 97.5, 'left_px_sheet' => 93, 'top_px_sheet' => 98, 'width_mm' => 24, 'align' => 'L'],
            'cobertura' => ['left_px' => 64, 'top_px' => 110, 'left_px_sheet' => 62, 'top_px_sheet' => 110, 'width_mm' => 30, 'align' => 'L'],
            'desde' => ['left_px' => 213, 'top_px' => 84.5, 'left_px_sheet' => 211, 'top_px_sheet' => 85, 'width_mm' => 20, 'align' => 'L'],
            'hasta' => ['left_px' => 213, 'top_px' => 96, 'left_px_sheet' => 211, 'top_px_sheet' => 96, 'width_mm' => 20, 'align' => 'L'],
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
        self::TEMPLATE_INDIVIDUAL_AFFILIATION => [
            'top_px' => 48,
            'right_px' => 24,
            'size_px' => 32,
        ],
    ];

    /**
     * @return array{width_mm: float, height_mm: float, orientation: 'P'|'L'}
     */
    public static function pageDimensions(string $templateKey): array
    {
        if ($templateKey === self::TEMPLATE_INDIVIDUAL_AFFILIATION) {
            return [
                'width_mm' => self::INDIVIDUAL_AFFILIATION_SHEET_UNIT_WIDTH_MM,
                'height_mm' => self::INDIVIDUAL_AFFILIATION_SHEET_UNIT_HEIGHT_MM,
                'orientation' => 'L',
            ];
        }

        return [
            'width_mm' => self::WIDTH_MM,
            'height_mm' => self::HEIGHT_MM,
            'orientation' => 'P',
        ];
    }

    /**
     * @return array{width_mm: float, height_mm: float, orientation: 'P'}
     */
    public static function sheetDimensions(): array
    {
        return [
            'width_mm' => self::WIDTH_MM,
            'height_mm' => self::HEIGHT_MM,
            'orientation' => 'P',
        ];
    }

    /**
     * @return array{width_mm: float, height_mm: float}
     */
    public static function individualAffiliationUnitDimensions(bool $singleAffiliate): array
    {
        if ($singleAffiliate) {
            return [
                'width_mm' => self::INDIVIDUAL_AFFILIATION_SINGLE_UNIT_WIDTH_MM,
                'height_mm' => self::INDIVIDUAL_AFFILIATION_SINGLE_UNIT_HEIGHT_MM,
            ];
        }

        return [
            'width_mm' => self::INDIVIDUAL_AFFILIATION_SHEET_UNIT_WIDTH_MM,
            'height_mm' => self::INDIVIDUAL_AFFILIATION_SHEET_UNIT_HEIGHT_MM,
        ];
    }

    /**
     * Origen (x, y) del carnet unitario sobre hoja A4 vertical.
     *
     * @return array{x_mm: float, y_mm: float}
     */
    public static function sheetCardOrigin(int $slotIndex, bool $singleAffiliate): array
    {
        $unit = self::individualAffiliationUnitDimensions($singleAffiliate);

        if ($singleAffiliate) {
            return [
                'x_mm' => round((self::WIDTH_MM - $unit['width_mm']) / 2, 2),
                'y_mm' => round((self::HEIGHT_MM - $unit['height_mm']) / 2, 2),
            ];
        }

        $slot = max(0, $slotIndex) % self::CARDS_PER_SHEET;
        $column = $slot % self::SHEET_COLUMNS;
        $row = intdiv($slot, self::SHEET_COLUMNS);

        $gridWidth = (self::SHEET_COLUMNS * $unit['width_mm']) + ((self::SHEET_COLUMNS - 1) * self::SHEET_GRID_GAP_MM);
        $gridHeight = (self::SHEET_ROWS * $unit['height_mm']) + ((self::SHEET_ROWS - 1) * self::SHEET_GRID_GAP_MM);
        $originX = (self::WIDTH_MM - $gridWidth) / 2;
        $originY = (self::HEIGHT_MM - $gridHeight) / 2;

        return [
            'x_mm' => round($originX + ($column * ($unit['width_mm'] + self::SHEET_GRID_GAP_MM)), 2),
            'y_mm' => round($originY + ($row * ($unit['height_mm'] + self::SHEET_GRID_GAP_MM)), 2),
        ];
    }

    public static function canvasWidthPx(string $templateKey): float
    {
        return $templateKey === self::TEMPLATE_INDIVIDUAL_AFFILIATION
            ? self::INDIVIDUAL_AFFILIATION_SHEET_CANVAS_WIDTH_PX
            : self::CANVAS_WIDTH_PX;
    }

    public static function fontSizePt(string $templateKey, bool $singleAffiliate = false): float
    {
        if ($templateKey === self::TEMPLATE_INDIVIDUAL_AFFILIATION) {
            return $singleAffiliate
                ? self::INDIVIDUAL_AFFILIATION_SINGLE_FONT_SIZE_PT
                : self::INDIVIDUAL_AFFILIATION_SHEET_FONT_SIZE_PT;
        }

        return self::FONT_SIZE_PT;
    }

    public static function fontSizePtForField(string $templateKey, string $field, bool $singleAffiliate = false): float
    {
        if ($templateKey === self::TEMPLATE_INDIVIDUAL_AFFILIATION
            && $field === 'code'
            && $singleAffiliate) {
            return self::INDIVIDUAL_AFFILIATION_SINGLE_CODE_FONT_SIZE_PT;
        }

        return self::fontSizePt($templateKey, $singleAffiliate);
    }

    public static function individualAffiliationStampScale(bool $singleAffiliate): float
    {
        if (! $singleAffiliate) {
            return 1.0;
        }

        return self::INDIVIDUAL_AFFILIATION_SINGLE_UNIT_WIDTH_MM / self::INDIVIDUAL_AFFILIATION_SHEET_UNIT_WIDTH_MM;
    }

    /**
     * @return array{x: float, y: float, width_mm: float, align: 'L'|'R'|'C'}
     */
    public static function fieldPosition(
        string $field,
        string $templateKey = self::TEMPLATE_INCLUSION,
        float $offsetXmm = 0.0,
        float $offsetYmm = 0.0,
        float $scale = 1.0,
        bool $singleAffiliate = true,
    ): array {
        $definition = self::FIELD_POSITIONS_BY_TEMPLATE[$templateKey][$field] ?? null;

        if ($definition === null) {
            return ['x' => $offsetXmm, 'y' => $offsetYmm, 'width_mm' => 40.0, 'align' => 'L'];
        }

        $topPx = $definition['top_px'];
        if (! $singleAffiliate && isset($definition['top_px_sheet'])) {
            $topPx = $definition['top_px_sheet'];
        }

        $y = (self::pxToMm($topPx) * $scale) + $offsetYmm;

        if (isset($definition['left_px'])) {
            $leftPx = $definition['left_px'];
            if (! $singleAffiliate && isset($definition['left_px_sheet'])) {
                $leftPx = $definition['left_px_sheet'];
            }

            return [
                'x' => (self::pxToMm($leftPx) * $scale) + $offsetXmm,
                'y' => $y,
                'width_mm' => $definition['width_mm'] * $scale,
                'align' => $definition['align'],
            ];
        }

        $canvasWidthPx = self::canvasWidthPx($templateKey);
        $rightEdgeMm = self::pxToMm($canvasWidthPx - $definition['right_px']) * $scale;

        return [
            'x' => $rightEdgeMm - ($definition['width_mm'] * $scale) + $offsetXmm,
            'y' => $y,
            'width_mm' => $definition['width_mm'] * $scale,
            'align' => $definition['align'],
        ];
    }

    /**
     * @return array{x_mm: float, y_mm: float, size_mm: float}|null
     */
    public static function qrPosition(
        string $templateKey,
        float $offsetXmm = 0.0,
        float $offsetYmm = 0.0,
        float $scale = 1.0,
    ): ?array {
        $definition = self::QR_POSITIONS_BY_TEMPLATE[$templateKey] ?? null;

        if ($definition === null) {
            return null;
        }

        $canvasWidthPx = self::canvasWidthPx($templateKey);
        $sizeMm = self::pxToMm($definition['size_px']) * $scale;
        $xMm = (self::pxToMm($canvasWidthPx - $definition['right_px'] - $definition['size_px']) * $scale) + $offsetXmm;
        $yMm = (self::pxToMm($definition['top_px']) * $scale) + $offsetYmm;

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
