<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

/**
 * Decide si la tarifa y el total grupal caben debajo de la matriz de beneficios
 * en la misma hoja A4. DomPDF, al partir una celda, deja el título en una hoja
 * y la tabla pegada al borde de la siguiente.
 */
final class PlanGeneratorPdfPagination
{
    private const PAGE_HEIGHT_PT = 841.89;

    private const TOP_PADDING_PT = 56.69;

    private const BOTTOM_GUARD_PT = 36.0;

    private const HEADER_BLOCK_PT = 64.0;

    private const PROPOSAL_BLOCK_PT = 112.0;

    /** Título de sección: 8 px arriba, 4 px abajo y la línea de 7 pt (`.section-title` en la plantilla). */
    private const SECTION_TITLE_PT = 18.0;

    private const FOOTER_BLOCK_PT = 20.0;

    private const CALC_PADDING_TOP_PT = 8.5;

    /** 8 mm: el `padding-bottom` de `.pdf-plan-calc-cell` en la plantilla. */
    private const CALC_PADDING_BOTTOM_PT = 22.68;

    private const MATRIX_FONT_PT = 6.5;

    /** Letra de la matriz de beneficios en el PDF (`table.pdf-benefits-table`). */
    private const BENEFITS_FONT_PT = 5.5;

    private const LINE_HEIGHT_FACTOR = 1.28;

    private const CELL_PADDING_Y_PT = 4.0;

    private const CELL_PADDING_X_PT = 6.0;

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    /** Secciones del bloque de cálculos, en el orden en que se imprimen. */
    public const SECTION_RATES = 'rates';

    public const SECTION_GROUP = 'group';

    public const SECTION_CONDITIONS = 'conditions';

    /**
     * Si alguna sección del bloque de cálculos pasa a la hoja siguiente.
     *
     * @param  array<int|string, mixed>  $columns
     * @param  array<int|string, mixed>  $rows
     * @param  array<int|string, mixed>  $rateRows
     */
    public static function calculationsStartOnNextPage(
        array $columns,
        array $rows,
        array $rateRows,
        bool $includeMonthlyTotal,
        string $populationUnitLabel = 'Población',
        string $conditions = '',
        mixed $groupTotalHiddenRows = [],
    ): bool {
        $sections = self::sections($conditions);

        return self::sectionsOnFirstPage($columns, $rows, $rateRows, $includeMonthlyTotal, $populationUnitLabel, $conditions, $groupTotalHiddenRows) < count($sections);
    }

    /**
     * Secciones que se imprimen, en orden.
     *
     * @return list<string>
     */
    public static function sections(string $conditions = ''): array
    {
        $sections = [self::SECTION_RATES, self::SECTION_GROUP];

        if (PlanGeneratorConditions::normalize($conditions) !== '') {
            $sections[] = self::SECTION_CONDITIONS;
        }

        return $sections;
    }

    /**
     * Cuántas secciones del bloque de cálculos caben en la hoja de la matriz.
     *
     * Antes el bloque entero (tarifa, total y condiciones) saltaba de hoja si no
     * cabía completo, y dejaba media primera hoja vacía. Ahora cada sección se
     * decide por separado y en orden: las que caben se quedan con la matriz (la
     * tarifa y el total, que es lo que el cliente busca primero) y solo las demás
     * pasan a la hoja siguiente. Ninguna sección se parte.
     *
     * @param  array<int|string, mixed>  $columns
     * @param  array<int|string, mixed>  $rows
     * @param  array<int|string, mixed>  $rateRows
     */
    public static function sectionsOnFirstPage(
        array $columns,
        array $rows,
        array $rateRows,
        bool $includeMonthlyTotal,
        string $populationUnitLabel = 'Población',
        string $conditions = '',
        mixed $groupTotalHiddenRows = [],
    ): int {
        $columnCount = max(1, count($columns));
        $planWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::planColumnWidthMm($columnCount));
        $leadWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::leadWidthMm());
        $rateAgeWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::rateAgeWidthMm());
        $ratePopWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::ratePopWidthMm());

        $heights = [
            self::SECTION_RATES => self::ratesSectionHeight($columns, $rateRows, $populationUnitLabel, $rateAgeWidthPt, $ratePopWidthPt, $planWidthPt),
            self::SECTION_GROUP => self::groupSectionHeight($columns, $includeMonthlyTotal, $groupTotalHiddenRows, $leadWidthPt, $planWidthPt),
            self::SECTION_CONDITIONS => self::conditionsBlockHeight($conditions),
        ];

        $available = self::PAGE_HEIGHT_PT
            - self::TOP_PADDING_PT
            - self::HEADER_BLOCK_PT
            - self::PROPOSAL_BLOCK_PT
            - self::SECTION_TITLE_PT
            - self::benefitsTableHeight($columns, $rows, $leadWidthPt, $planWidthPt)
            - self::CALC_PADDING_TOP_PT
            - self::CALC_PADDING_BOTTOM_PT
            - self::FOOTER_BLOCK_PT
            - self::BOTTOM_GUARD_PT;

        $fits = 0;

        foreach (self::sections($conditions) as $section) {
            $available -= $heights[$section];

            if ($available < 0) {
                break;
            }

            $fits++;
        }

        return $fits;
    }

    /**
     * Alto de la matriz de beneficios. Se mide con su propia letra (5,5 pt,
     * `table.pdf-benefits-table`), no con la de las tablas de cálculo: con 6,5 pt los
     * beneficios largos parecían de dos líneas y la hoja se daba por llena antes de
     * tiempo (estimaba 512 pt donde el PDF real termina en 447).
     *
     * @param  array<int|string, mixed>  $columns
     * @param  array<int|string, mixed>  $rows
     */
    private static function benefitsTableHeight(array $columns, array $rows, float $leadWidthPt, float $planWidthPt): float
    {
        $headerLines = max(
            self::lineCount('BENEFICIOS DEL PLAN', self::textWidthPt($leadWidthPt), true, self::BENEFITS_FONT_PT),
            self::tallestPlanHeaderLines($columns, $planWidthPt, self::BENEFITS_FONT_PT),
        );

        $height = self::mmToPt(8) + self::benefitRowHeight($headerLines);

        if ($rows === []) {
            return $height + self::benefitRowHeight(1);
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines = self::lineCount((string) ($row['benefit_label'] ?? '—'), self::textWidthPt($leadWidthPt), false, self::BENEFITS_FONT_PT);

            foreach ($columns as $column) {
                $columnKey = (string) ($column['column_key'] ?? '');
                $cell = (array) data_get($row, "cells.{$columnKey}", []);
                $display = PlanGeneratorPreviewBuilder::benefitCellDisplay(
                    (bool) ($cell['is_selected'] ?? false),
                    $cell['coverage_amount'] ?? null,
                );
                $label = $display === 'amount'
                    ? 'US$ '.PlanGeneratorPreviewBuilder::formatCoverageAmount((float) $cell['coverage_amount'])
                    : '—';
                $lines = max($lines, self::lineCount($label, self::textWidthPt($planWidthPt), false, self::BENEFITS_FONT_PT));
            }

            $height += self::benefitRowHeight($lines);
        }

        return $height;
    }

    /**
     * Medido en el PDF real: 14,6 pt una fila de una línea (la marca ✓ de 6,5 pt
     * manda en el alto) y ~8 pt cada línea extra; se redondea hacia arriba.
     */
    private static function benefitRowHeight(int $lines): float
    {
        return 15.0 + (max(1, $lines) - 1) * 8.0;
    }

    /**
     * @param  array<int|string, mixed>  $columns
     * @param  array<int|string, mixed>  $rateRows
     */
    private static function ratesSectionHeight(
        array $columns,
        array $rateRows,
        string $populationUnitLabel,
        float $rateAgeWidthPt,
        float $ratePopWidthPt,
        float $planWidthPt,
    ): float {
        $header = max(
            self::lineCount('TARIFA INDIVIDUAL ANUAL', self::textWidthPt($rateAgeWidthPt), true),
            self::lineCount(mb_strtoupper($populationUnitLabel), self::textWidthPt($ratePopWidthPt), true),
            self::tallestPlanHeaderLines($columns, $planWidthPt),
        );

        $body = $rateRows === [] ? self::bodyRowHeight(1) : 0.0;

        foreach ($rateRows as $rateRow) {
            if (! is_array($rateRow)) {
                continue;
            }

            $lines = self::lineCount((string) ($rateRow['age_range_label'] ?? '—'), self::textWidthPt($rateAgeWidthPt), false);

            foreach ($columns as $column) {
                $columnKey = (string) ($column['column_key'] ?? '');
                $rate = data_get($rateRow, "cells.{$columnKey}.rate_amount");
                $label = is_numeric($rate)
                    ? PlanGeneratorPreviewBuilder::formatRateAmount((float) $rate)
                    : '—';
                $lines = max($lines, self::lineCount($label, self::textWidthPt($planWidthPt), true));
            }

            $body += self::bodyRowHeight($lines);
        }

        return self::SECTION_TITLE_PT + self::bodyRowHeight($header) + $body;
    }

    /**
     * @param  array<int|string, mixed>  $columns
     */
    private static function groupSectionHeight(array $columns, bool $includeMonthlyTotal, mixed $groupTotalHiddenRows, float $leadWidthPt, float $planWidthPt): float
    {
        $body = 0.0;

        foreach (PlanGeneratorGroupTotalCalculator::groupTotalRows($includeMonthlyTotal, $groupTotalHiddenRows) as $groupRow) {
            $body += self::bodyRowHeight(
                self::lineCount($groupRow['label'], self::textWidthPt($leadWidthPt), (bool) $groupRow['bold']),
            );
        }

        return self::SECTION_TITLE_PT + self::headerRowHeight('Total Grupal', $leadWidthPt, $columns, $planWidthPt) + $body;
    }

    private static function conditionsBlockHeight(string $conditions): float
    {
        $text = PlanGeneratorConditions::normalize($conditions);

        if ($text === '') {
            return 0.0;
        }

        $width = self::mmToPt(PlanGeneratorMatrixColumnLayout::PDF_CONTENT_WIDTH_MM) - 12.0;
        $height = self::SECTION_TITLE_PT;

        foreach (explode("\n", $text) as $line) {
            $height += self::bodyRowHeight(self::lineCount($line === '' ? ' ' : $line, $width, false));
        }

        return $height;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    private static function headerRowHeight(string $leadLabel, float $leadWidthPt, array $columns, float $planWidthPt): float
    {
        return self::bodyRowHeight(max(
            self::lineCount(mb_strtoupper($leadLabel), self::textWidthPt($leadWidthPt), true),
            self::tallestPlanHeaderLines($columns, $planWidthPt),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    private static function tallestPlanHeaderLines(array $columns, float $planWidthPt, float $fontPt = self::MATRIX_FONT_PT): int
    {
        $lines = 1;

        foreach ($columns as $column) {
            $lines = max(
                $lines,
                self::lineCount(mb_strtoupper((string) ($column['header_label'] ?? '—')), self::textWidthPt($planWidthPt), true, $fontPt),
            );
        }

        return $lines;
    }

    private static function bodyRowHeight(int $lines): float
    {
        return max(1, $lines) * self::MATRIX_FONT_PT * self::LINE_HEIGHT_FACTOR + self::CELL_PADDING_Y_PT + 1.0;
    }

    private static function textWidthPt(float $columnWidthPt): float
    {
        return max(8.0, $columnWidthPt - self::CELL_PADDING_X_PT);
    }

    private static function lineCount(string $text, float $maxWidth, bool $bold, float $fontPt = self::MATRIX_FONT_PT): int
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return 1;
        }

        $words = preg_split('/ /u', $text) ?: [];
        $lines = 1;
        $currentWidth = 0.0;
        $spaceWidth = self::textWidth(' ', $bold, $fontPt);

        foreach ($words as $word) {
            $wordWidth = self::textWidth($word, $bold, $fontPt);

            if ($wordWidth > $maxWidth) {
                if ($currentWidth > 0) {
                    $lines++;
                }

                $lines += (int) ceil($wordWidth / $maxWidth) - 1;
                $currentWidth = fmod($wordWidth, $maxWidth);

                if ($currentWidth <= 0.0) {
                    $currentWidth = $maxWidth;
                }

                continue;
            }

            $needed = $currentWidth <= 0.0 ? $wordWidth : $currentWidth + $spaceWidth + $wordWidth;

            if ($currentWidth > 0.0 && $needed > $maxWidth) {
                $lines++;
                $currentWidth = $wordWidth;

                continue;
            }

            $currentWidth = $needed;
        }

        return max(1, $lines);
    }

    private static function textWidth(string $text, bool $bold, float $fontPt = self::MATRIX_FONT_PT): float
    {
        $font = $bold ? self::boldFontPath() : self::regularFontPath();

        if (! is_file($font)) {
            return mb_strlen($text) * $fontPt * 0.56;
        }

        $box = imagettfbbox($fontPt, 0, $font, $text);

        if ($box === false) {
            return mb_strlen($text) * $fontPt * 0.56;
        }

        // imagettfbbox mide en píxeles a 96 ppp: sin pasar a puntos (× 72/96) cada
        // texto parecía un 33 % más ancho e inventaba saltos de línea.
        return abs($box[2] - $box[0]) * 72 / 96;
    }

    private static function regularFontPath(): string
    {
        return dirname(__DIR__, 3).'/vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';
    }

    private static function boldFontPath(): string
    {
        return dirname(__DIR__, 3).'/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
    }

    private static function mmToPt(float $millimeters): float
    {
        return $millimeters * 72 / 25.4;
    }
}
