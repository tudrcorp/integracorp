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
     * Filas que el analista puede quitar del total grupal. La mensual no está:
     * la gobierna `include_monthly_total`.
     *
     * @var list<string>
     */
    public const HIDEABLE_ROWS = [self::ROW_ANNUAL, self::ROW_SEMESTRAL, self::ROW_TRIMESTRAL];

    /**
     * Frecuencia de pago que ofrece cada fila al registrar la empresa.
     *
     * @var array<string, string>
     */
    public const ROW_PAYMENT_FREQUENCIES = [
        self::ROW_ANNUAL => 'ANUAL',
        self::ROW_SEMESTRAL => 'SEMESTRAL',
        self::ROW_TRIMESTRAL => 'TRIMESTRAL',
        self::ROW_MENSUAL => 'MENSUAL',
    ];

    /**
     * Filas visibles del total grupal, en su orden fijo.
     *
     * Nunca devuelve una tabla vacía: si se quitaron todas, queda la anual, que
     * es el precio de referencia de la cotización.
     *
     * @param  mixed  $hiddenRows  claves quitadas (`group_total_hidden_rows`)
     * @return array<int, array{key: string, label: string, bold: bool}>
     */
    public static function groupTotalRows(bool $includeMonthlyTotal = false, mixed $hiddenRows = []): array
    {
        $hidden = self::normalizeHiddenRows($hiddenRows);

        $rows = [
            ['key' => self::ROW_ANNUAL, 'label' => 'Tarifa anual', 'bold' => true],
            ['key' => self::ROW_SEMESTRAL, 'label' => 'Tarifa Semestral', 'bold' => false],
            ['key' => self::ROW_TRIMESTRAL, 'label' => 'Tarifa Trimestral', 'bold' => false],
        ];

        if ($includeMonthlyTotal) {
            $rows[] = ['key' => self::ROW_MENSUAL, 'label' => 'Total Mensual', 'bold' => false];
        }

        $visible = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ! in_array($row['key'], $hidden, true),
        ));

        return $visible !== [] ? $visible : [$rows[0]];
    }

    /**
     * Claves válidas y sin repetir. Llega del navegador (estado Livewire) o de
     * la columna JSON, así que se filtra contra la lista blanca.
     *
     * @return list<string>
     */
    public static function normalizeHiddenRows(mixed $hiddenRows): array
    {
        if (is_string($hiddenRows)) {
            $decoded = json_decode($hiddenRows, true);
            $hiddenRows = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($hiddenRows)) {
            return [];
        }

        return array_values(array_filter(
            self::HIDEABLE_ROWS,
            static fn (string $key): bool => in_array($key, $hiddenRows, true),
        ));
    }

    /**
     * Filas quitadas que se pueden volver a agregar, con su etiqueta. La
     * mensual no aparece: vuelve con el interruptor «Incluir cálculo mensual».
     *
     * @return list<array{key: string, label: string}>
     */
    public static function removedRows(bool $includeMonthlyTotal, mixed $hiddenRows): array
    {
        $visible = array_column(self::groupTotalRows($includeMonthlyTotal, $hiddenRows), 'key');
        $all = self::groupTotalRows(false);

        return array_values(array_map(
            static fn (array $row): array => ['key' => $row['key'], 'label' => $row['label']],
            array_filter($all, static fn (array $row): bool => ! in_array($row['key'], $visible, true)),
        ));
    }

    /**
     * Frecuencias de pago que admite la cotización: las de sus filas visibles.
     *
     * @return array<string, string>
     */
    public static function paymentFrequencies(bool $includeMonthlyTotal, mixed $hiddenRows): array
    {
        $options = [];

        foreach (self::groupTotalRows($includeMonthlyTotal, $hiddenRows) as $row) {
            $frequency = self::ROW_PAYMENT_FREQUENCIES[$row['key']];
            $options[$frequency] = $frequency;
        }

        return $options;
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
