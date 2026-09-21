<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

/**
 * Ajuste global de tarifas de una cotización: un porcentaje de aumento o de
 * descuento aplicado a todas las tarifas de las columnas elegidas.
 *
 * El porcentaje es **interno**. La cotización —PDF y vista previa— muestra
 * únicamente `rate_amount`, ya ajustado; el cliente nunca ve el porcentaje.
 *
 * El cálculo parte siempre de `base_rate_amount`, la tarifa original congelada
 * en la primera aplicación. Por eso el ajuste es idempotente y reversible:
 * pasar de -10 % a -15 % da -15 % del original y no -23,5 %, y volver a 0 %
 * restaura el monto exacto. Si el analista teclea una tarifa a mano mientras
 * hay un ajuste vigente, `resolveBaseAmount()` lo detecta —el monto ya no
 * coincide con base × porcentaje— y adopta lo tecleado como nueva base.
 */
final class PlanGeneratorRateAdjustment
{
    public const MIN_PERCENT = -100.0;

    public const MAX_PERCENT = 1000.0;

    /**
     * Aplica el porcentaje a las columnas indicadas.
     *
     * @param  array<int, mixed>  $columns
     * @param  array<string, mixed>  $rateRows
     * @param  list<string>  $columnKeys
     * @return array{
     *     columns: list<array{column_key: string, header_label: string, rate_adjustment_percent: float|null}>,
     *     rate_rows: array<string, mixed>,
     *     adjusted_columns: int,
     *     adjusted_cells: int
     * }
     */
    public static function apply(array $columns, array $rateRows, float $percent, array $columnKeys): array
    {
        $percent = self::clampPercent($percent);
        $targets = array_values(array_unique(array_map('strval', $columnKeys)));
        $normalized = PlanGeneratorMatrixState::keyedColumns($columns);

        $adjustedColumns = 0;
        $adjustedCells = 0;

        foreach ($normalized as $index => $column) {
            if (! in_array($column['column_key'], $targets, true)) {
                continue;
            }

            $activePercent = self::percentFor($column);
            $normalized[$index]['rate_adjustment_percent'] = $percent === 0.0 ? null : $percent;
            $adjustedColumns++;

            foreach ($rateRows as $rowKey => $rateRow) {
                if (! is_array($rateRow)) {
                    continue;
                }

                $columnKey = $column['column_key'];
                $cell = (array) ($rateRow['cells'][$columnKey] ?? []);

                $base = self::resolveBaseAmount(
                    self::parseAmount($cell['rate_amount'] ?? null),
                    self::parseAmount($cell['base_rate_amount'] ?? null),
                    $activePercent,
                );

                if ($base === null) {
                    // Celda vacía: no hay tarifa que ajustar y tampoco base que
                    // congelar. Se deja intacta para no inventar un monto.
                    $rateRows[$rowKey]['cells'][$columnKey]['base_rate_amount'] = null;

                    continue;
                }

                $rateRows[$rowKey]['cells'][$columnKey]['base_rate_amount'] = $base;
                $rateRows[$rowKey]['cells'][$columnKey]['rate_amount'] = self::adjustedAmount($base, $percent);
                $adjustedCells++;
            }
        }

        return [
            'columns' => $normalized,
            'rate_rows' => $rateRows,
            'adjusted_columns' => $adjustedColumns,
            'adjusted_cells' => $adjustedCells,
        ];
    }

    /**
     * Tarifa que ve el cliente: la base con el porcentaje aplicado, redondeada
     * al entero más cercano porque así se imprimen las tarifas en el PDF.
     */
    public static function adjustedAmount(?float $base, ?float $percent): ?float
    {
        if ($base === null) {
            return null;
        }

        $percent = self::clampPercent((float) ($percent ?? 0.0));

        return (float) round($base * (1 + ($percent / 100)));
    }

    /**
     * Base sobre la que se calcula el ajuste.
     *
     * Sin base congelada, la tarifa actual pasa a ser la base. Con base
     * congelada se comprueba que la tarifa actual siga siendo el resultado del
     * ajuste vigente: si no lo es, el analista la editó a mano y lo tecleado
     * manda.
     */
    public static function resolveBaseAmount(?float $currentAmount, ?float $storedBase, ?float $activePercent): ?float
    {
        if ($storedBase === null) {
            return $currentAmount;
        }

        if ($currentAmount === null) {
            return null;
        }

        return self::adjustedAmount($storedBase, $activePercent) === $currentAmount
            ? $storedBase
            : $currentAmount;
    }

    /**
     * Porcentaje vigente de una columna, o null si no tiene ajuste.
     *
     * @param  array<string, mixed>  $column
     */
    public static function percentFor(array $column): ?float
    {
        $percent = $column['rate_adjustment_percent'] ?? null;

        if (! is_numeric($percent)) {
            return null;
        }

        $percent = self::clampPercent((float) $percent);

        return $percent === 0.0 ? null : $percent;
    }

    /**
     * Opciones del selector de columnas del modal, con el ajuste vigente a la
     * vista para que el analista sepa sobre qué está actuando.
     *
     * @param  array<int, mixed>  $columns
     * @return array<string, string>
     */
    public static function columnOptions(array $columns): array
    {
        $options = [];

        foreach (PlanGeneratorMatrixState::keyedColumns($columns) as $column) {
            $label = $column['header_label'] !== '' ? $column['header_label'] : 'Columna sin nombre';
            $percent = self::percentFor($column);

            $options[$column['column_key']] = $percent === null
                ? $label
                : $label.' (hoy '.self::formatPercent($percent).')';
        }

        return $options;
    }

    /**
     * Resumen interno de los ajustes vigentes, para mostrarlo en el editor y en
     * la ficha del plan. No se usa en el PDF.
     *
     * @param  array<int, mixed>  $columns
     */
    public static function summary(array $columns): ?string
    {
        $parts = [];

        foreach (PlanGeneratorMatrixState::keyedColumns($columns) as $column) {
            $percent = self::percentFor($column);

            if ($percent === null) {
                continue;
            }

            $label = $column['header_label'] !== '' ? $column['header_label'] : 'Columna sin nombre';
            $parts[] = $label.' '.self::formatPercent($percent);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public static function formatPercent(float $percent): string
    {
        $formatted = rtrim(rtrim(number_format(abs($percent), 2, ',', '.'), '0'), ',');

        return ($percent < 0 ? '−' : '+').$formatted.'%';
    }

    public static function clampPercent(float $percent): float
    {
        return max(self::MIN_PERCENT, min(self::MAX_PERCENT, $percent));
    }

    public static function parseAmount(mixed $amount): ?float
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return null;
        }

        return (float) $amount;
    }
}
