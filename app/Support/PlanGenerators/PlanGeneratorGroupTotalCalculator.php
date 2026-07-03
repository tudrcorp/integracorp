<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

final class PlanGeneratorGroupTotalCalculator
{
    public const ROW_ANNUAL = 'annual';

    public const ROW_SEMESTRAL = 'semestral';

    public const ROW_TRIMESTRAL = 'trimestral';

    public const ROW_MENSUAL = 'mensual';

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $rateRows
     * @return array{
     *     annual: array<string, float>,
     *     semestral: array<string, float>,
     *     trimestral: array<string, float>,
     *     mensual: array<string, float>
     * }
     */
    public static function totalsByColumn(array $columns, array $rateRows): array
    {
        $columns = PlanGeneratorMatrixState::normalizeColumns($columns);
        $annualByColumn = [];

        foreach (PlanGeneratorMatrixState::extractColumnKeys($columns) as $columnKey) {
            $annualByColumn[$columnKey] = self::annualTotalForColumn($columnKey, $rateRows);
        }

        $semestralByColumn = [];
        $trimestralByColumn = [];
        $mensualByColumn = [];

        foreach ($annualByColumn as $columnKey => $annualTotal) {
            $semestralByColumn[$columnKey] = $annualTotal / 2;
            $trimestralByColumn[$columnKey] = $annualTotal / 4;
            $mensualByColumn[$columnKey] = $annualTotal / 12;
        }

        return [
            self::ROW_ANNUAL => $annualByColumn,
            self::ROW_SEMESTRAL => $semestralByColumn,
            self::ROW_TRIMESTRAL => $trimestralByColumn,
            self::ROW_MENSUAL => $mensualByColumn,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, bold: bool}>
     */
    public static function groupTotalRows(bool $includeMonthlyTotal = false): array
    {
        $rows = [
            ['key' => self::ROW_ANNUAL, 'label' => 'Tarifa anual', 'bold' => true],
            ['key' => self::ROW_SEMESTRAL, 'label' => 'Tarifa Semestral', 'bold' => false],
            ['key' => self::ROW_TRIMESTRAL, 'label' => 'Tarifa Trimestral', 'bold' => false],
        ];

        if ($includeMonthlyTotal) {
            $rows[] = ['key' => self::ROW_MENSUAL, 'label' => 'Total Mensual', 'bold' => false];
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function annualTotalForColumn(string $columnKey, array $rateRows): float
    {
        $total = 0.0;

        foreach ($rateRows as $rateRow) {
            if (! is_array($rateRow)) {
                continue;
            }

            $population = (int) ($rateRow['population'] ?? 0);
            if ($population <= 0) {
                continue;
            }

            $rate = self::parseAmount(data_get($rateRow, "cells.{$columnKey}.rate_amount"));
            $total += $rate * $population;
        }

        return $total;
    }

    public static function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);
        $normalized = str_replace([' ', '$'], '', $normalized);

        if ($normalized === '' || ! is_numeric(str_replace(',', '.', $normalized))) {
            return 0.0;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }

    public static function formatGroupTotal(?float $amount): string
    {
        if ($amount === null || $amount <= 0) {
            return '—';
        }

        return '$'.number_format($amount, 0, ',', '.');
    }
}
