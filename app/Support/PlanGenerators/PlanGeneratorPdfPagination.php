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

    private const SECTION_TITLE_PT = 22.0;

    private const FOOTER_BLOCK_PT = 20.0;

    private const CALC_PADDING_TOP_PT = 8.5;

    private const CALC_PADDING_BOTTOM_PT = 45.35;

    private const MATRIX_FONT_PT = 6.5;

    private const LINE_HEIGHT_FACTOR = 1.28;

    private const CELL_PADDING_Y_PT = 4.0;

    private const CELL_PADDING_X_PT = 6.0;

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function calculationsStartOnNextPage(
        array $columns,
        array $rows,
        array $rateRows,
        bool $includeMonthlyTotal,
        string $populationUnitLabel = 'Población',
    ): bool {
        $columnCount = max(1, count($columns));
        $planWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::planColumnWidthMm($columnCount));
        $leadWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::leadWidthMm());
        $rateAgeWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::rateAgeWidthMm());
        $ratePopWidthPt = self::mmToPt((float) PlanGeneratorMatrixColumnLayout::ratePopWidthMm());

        $benefitsHeight = self::benefitsTableHeight($columns, $rows, $leadWidthPt, $planWidthPt);
        $calculationsHeight = self::calculationsBlockHeight(
            $columns,
            $rateRows,
            $includeMonthlyTotal,
            $populationUnitLabel,
            $leadWidthPt,
            $rateAgeWidthPt,
            $ratePopWidthPt,
            $planWidthPt,
        );

        $used = self::TOP_PADDING_PT
            + self::HEADER_BLOCK_PT
            + self::PROPOSAL_BLOCK_PT
            + self::SECTION_TITLE_PT
            + $benefitsHeight
            + $calculationsHeight
            + self::BOTTOM_GUARD_PT;

        return $used > self::PAGE_HEIGHT_PT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $rows
     */
    private static function benefitsTableHeight(array $columns, array $rows, float $leadWidthPt, float $planWidthPt): float
    {
        $height = self::mmToPt(8) + self::headerRowHeight(
            'Beneficios del Plan',
            $leadWidthPt,
            $columns,
            $planWidthPt,
        );

        if ($rows === []) {
            return $height + self::bodyRowHeight(1);
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines = self::lineCount((string) ($row['benefit_label'] ?? '—'), self::textWidthPt($leadWidthPt), false);

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
                $lines = max($lines, self::lineCount($label, self::textWidthPt($planWidthPt), false));
            }

            $height += self::bodyRowHeight($lines);
        }

        return $height;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    private static function calculationsBlockHeight(
        array $columns,
        array $rateRows,
        bool $includeMonthlyTotal,
        string $populationUnitLabel,
        float $leadWidthPt,
        float $rateAgeWidthPt,
        float $ratePopWidthPt,
        float $planWidthPt,
    ): float {
        $ratesHeader = max(
            self::lineCount('TARIFA INDIVIDUAL ANUAL', self::textWidthPt($rateAgeWidthPt), true),
            self::lineCount(mb_strtoupper($populationUnitLabel), self::textWidthPt($ratePopWidthPt), true),
            self::tallestPlanHeaderLines($columns, $planWidthPt),
        );

        $ratesBody = 0.0;

        if ($rateRows === []) {
            $ratesBody = self::bodyRowHeight(1);
        }

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

            $ratesBody += self::bodyRowHeight($lines);
        }

        $groupHeader = self::headerRowHeight('Total Grupal', $leadWidthPt, $columns, $planWidthPt);
        $groupBody = 0.0;

        foreach (PlanGeneratorGroupTotalCalculator::groupTotalRows($includeMonthlyTotal) as $groupRow) {
            $groupBody += self::bodyRowHeight(
                self::lineCount($groupRow['label'], self::textWidthPt($leadWidthPt), (bool) $groupRow['bold']),
            );
        }

        return self::CALC_PADDING_TOP_PT
            + self::SECTION_TITLE_PT
            + self::bodyRowHeight($ratesHeader)
            + $ratesBody
            + self::SECTION_TITLE_PT
            + $groupHeader
            + $groupBody
            + self::FOOTER_BLOCK_PT
            + self::CALC_PADDING_BOTTOM_PT;
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
    private static function tallestPlanHeaderLines(array $columns, float $planWidthPt): int
    {
        $lines = 1;

        foreach ($columns as $column) {
            $lines = max(
                $lines,
                self::lineCount(mb_strtoupper((string) ($column['header_label'] ?? '—')), self::textWidthPt($planWidthPt), true),
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

    private static function lineCount(string $text, float $maxWidth, bool $bold): int
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return 1;
        }

        $words = preg_split('/ /u', $text) ?: [];
        $lines = 1;
        $currentWidth = 0.0;
        $spaceWidth = self::textWidth(' ', $bold);

        foreach ($words as $word) {
            $wordWidth = self::textWidth($word, $bold);

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

    private static function textWidth(string $text, bool $bold): float
    {
        $font = $bold ? self::boldFontPath() : self::regularFontPath();

        if (! is_file($font)) {
            return mb_strlen($text) * self::MATRIX_FONT_PT * 0.56;
        }

        $box = imagettfbbox(self::MATRIX_FONT_PT, 0, $font, $text);

        if ($box === false) {
            return mb_strlen($text) * self::MATRIX_FONT_PT * 0.56;
        }

        return abs($box[2] - $box[0]);
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
