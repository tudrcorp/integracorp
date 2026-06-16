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
     * @return array{rate_amount: null}
     */
    public static function emptyRateCell(): array
    {
        return [
            'rate_amount' => null,
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
     * @return list<array{column_key: string, header_label: string}>
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
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $columns
     */
    public static function columnsFingerprint(array $columns): string
    {
        $normalized = self::normalizeColumns($columns);

        $parts = array_map(
            fn (array $column): string => $column['column_key'].'|'.$column['header_label'],
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
     * @return array<string, array{rate_amount: mixed}>
     */
    public static function orderRateCellsForColumns(array $cells, array $columns): array
    {
        $ordered = [];

        foreach (self::extractColumnKeys($columns) as $columnKey) {
            $cell = $cells[$columnKey] ?? null;

            $ordered[$columnKey] = is_array($cell)
                ? ['rate_amount' => $cell['rate_amount'] ?? null]
                : self::emptyRateCell();
        }

        return $ordered;
    }

    public static function newRowKey(): string
    {
        return (string) Str::uuid();
    }
}
