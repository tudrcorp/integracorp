<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use Illuminate\Support\Str;

final class PlanGeneratorMatrixState
{
    /**
     * @param  array<string|int, mixed>  $rows
     * @param  array<int, mixed>  $columns
     * @return array<string|int, mixed>
     */
    public static function ensureRowsHaveCells(array $rows, array $columns): array
    {
        foreach ($rows as $rowKey => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[$rowKey]['cells'] = self::orderBenefitCellsForColumns(
                (array) ($row['cells'] ?? []),
                $columns,
            );
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $columns
     * @return array<string, mixed>
     */
    /**
     * Marca o quita «Incluido» en todas las celdas, sin tocar el monto de cobertura.
     *
     * @param  array<string|int, mixed>  $rows
     * @param  array<int, mixed>  $columns
     * @return array<string|int, mixed>
     */
    public static function withBenefitsIncluded(array $rows, array $columns, bool $included): array
    {
        $rows = self::ensureRowsHaveCells($rows, $columns);

        foreach ($rows as $rowKey => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ((array) ($row['cells'] ?? []) as $columnKey => $cell) {
                if (! is_array($cell)) {
                    continue;
                }

                $rows[$rowKey]['cells'][$columnKey]['is_selected'] = $included;
            }
        }

        return $rows;
    }

    public static function emptyCellsForColumns(array $columns): array
    {
        $cells = [];

        foreach (self::extractColumnKeys($columns) as $columnKey) {
            $cells[$columnKey] = self::emptyCell();
        }

        return $cells;
    }

    /**
     * @return array{is_selected: bool, coverage_amount: null}
     */
    public static function emptyCell(): array
    {
        return [
            'is_selected' => false,
            'coverage_amount' => null,
        ];
    }

    /**
     * @param  array<int, mixed>  $columns
     * @return list<string>
     */
    public static function extractColumnKeys(array $columns): array
    {
        $keys = [];

        foreach ($columns as $column) {
            if (! is_array($column) || ! filled($column['column_key'] ?? null)) {
                continue;
            }

            $keys[] = (string) $column['column_key'];
        }

        return $keys;
    }

    /**
     * `base_rate_amount` es la tarifa original congelada por el ajuste global
     * de tarifas (ver PlanGeneratorRateAdjustment). Es dato interno: la
     * cotización solo muestra `rate_amount`.
     *
     * @return array{rate_amount: null, base_rate_amount: null}
     */
    public static function emptyRateCell(): array
    {
        return [
            'rate_amount' => null,
            'base_rate_amount' => null,
        ];
    }

    /**
     * @param  array<int, mixed>  $columns
     * @return array<string, mixed>
     */
    public static function emptyRateCellsForColumns(array $columns): array
    {
        $cells = [];

        foreach (self::extractColumnKeys($columns) as $columnKey) {
            $cells[$columnKey] = self::emptyRateCell();
        }

        return $cells;
    }

    /**
     * @param  array<string|int, mixed>  $rateRows
     * @param  array<int, mixed>  $columns
     * @return array<string|int, mixed>
     */
    public static function ensureRateRowsHaveCells(array $rateRows, array $columns): array
    {
        foreach ($rateRows as $rowKey => $rateRow) {
            if (! is_array($rateRow)) {
                continue;
            }

            $rateRows[$rowKey]['cells'] = self::orderRateCellsForColumns(
                (array) ($rateRow['cells'] ?? []),
                $columns,
            );
        }

        return $rateRows;
    }

    /**
     * @param  array<int, mixed>  $columns
     * @return list<array{column_key: string, header_label: string, rate_adjustment_percent: float|null}>
     */
    public static function normalizeColumns(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $column) {
            if (! is_array($column) || ! filled($column['header_label'] ?? null)) {
                continue;
            }

            if (! filled($column['column_key'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'column_key' => (string) $column['column_key'],
                'header_label' => (string) $column['header_label'],
                'rate_adjustment_percent' => self::parseAdjustmentPercent($column['rate_adjustment_percent'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * Porcentaje del ajuste global de tarifas de una columna. Es dato interno y
     * nunca llega al PDF: las plantillas solo leen `header_label`.
     */
    public static function parseAdjustmentPercent(mixed $percent): ?float
    {
        return is_numeric($percent) ? (float) $percent : null;
    }

    /**
     * Columnas listas para pintar cuando el analista puede renombrarlas en el
     * propio editor.
     *
     * A diferencia de `normalizeColumns()`, conserva las columnas sin
     * encabezado: si se descartaran, borrar el texto del input haría
     * desaparecer la columna de la pantalla y el analista no tendría forma de
     * volver a nombrarla. El descarte sigue ocurriendo al guardar.
     *
     * @param  array<int, mixed>  $columns
     * @return list<array{column_key: string, header_label: string, rate_adjustment_percent: float|null}>
     */
    public static function keyedColumns(array $columns): array
    {
        $keyed = [];

        foreach ($columns as $column) {
            if (! is_array($column) || ! filled($column['column_key'] ?? null)) {
                continue;
            }

            $keyed[] = [
                'column_key' => (string) $column['column_key'],
                'header_label' => (string) ($column['header_label'] ?? ''),
                'rate_adjustment_percent' => self::parseAdjustmentPercent($column['rate_adjustment_percent'] ?? null),
            ];
        }

        return $keyed;
    }

    /**
     * @param  array<int, mixed>  $columns
     */
    public static function columnsFingerprint(array $columns): string
    {
        $normalized = self::normalizeColumns($columns);

        $parts = array_map(
            fn (array $column): string => $column['column_key']
                .'|'.$column['header_label']
                .'|'.($column['rate_adjustment_percent'] ?? ''),
            $normalized,
        );

        return md5(implode('::', $parts));
    }

    /**
     * @param  array<string, mixed>  $cells
     * @param  array<int, mixed>  $columns
     * @return array<string, array{is_selected: bool, coverage_amount: mixed}>
     */
    public static function orderBenefitCellsForColumns(array $cells, array $columns): array
    {
        $ordered = [];

        foreach (self::extractColumnKeys($columns) as $columnKey) {
            $cell = $cells[$columnKey] ?? null;

            $ordered[$columnKey] = is_array($cell)
                ? [
                    'is_selected' => (bool) ($cell['is_selected'] ?? false),
                    'coverage_amount' => $cell['coverage_amount'] ?? null,
                ]
                : self::emptyCell();
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $cells
     * @param  array<int, mixed>  $columns
     * @return array<string, array{rate_amount: mixed, base_rate_amount: mixed}>
     */
    public static function orderRateCellsForColumns(array $cells, array $columns): array
    {
        $ordered = [];

        foreach (self::extractColumnKeys($columns) as $columnKey) {
            $cell = $cells[$columnKey] ?? null;

            $ordered[$columnKey] = is_array($cell)
                ? [
                    'rate_amount' => $cell['rate_amount'] ?? null,
                    'base_rate_amount' => $cell['base_rate_amount'] ?? null,
                ]
                : self::emptyRateCell();
        }

        return $ordered;
    }

    public static function newRowKey(): string
    {
        return (string) Str::uuid();
    }
}
