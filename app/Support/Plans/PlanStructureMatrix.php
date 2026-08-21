<?php

declare(strict_types=1);

namespace App\Support\Plans;

/**
 * Mantiene alineadas las matrices del armado de planes con las coberturas que
 * el analista definió en el paso anterior.
 *
 * El formulario tiene dos matrices con la misma forma: los costos límite por
 * beneficio y las tarifas por rango de edad. En ambas, las filas son entidades
 * (beneficio, rango) y las columnas son las coberturas del plan.
 *
 * Cada cobertura arrastra una clave estable (`coverage_key`) desde que se crea
 * en el formulario. Es lo que permite que, al volver al paso de coberturas y
 * agregar o quitar montos, las celdas ya escritas no se pierdan ni se corran de
 * columna: se emparejan por clave y no por posición.
 *
 * Todo acá es cálculo puro sobre arrays para que se pueda probar sin Filament
 * ni base de datos.
 */
final class PlanStructureMatrix
{
    /**
     * Columnas de la matriz, en el orden en que se muestran: por monto de
     * cobertura ascendente, que es como el analista lee la tabla y como
     * `QuotePdfCoverageTable` arma las columnas del PDF.
     *
     * @param  array<array-key, array<string, mixed>>  $planCoverages
     * @return list<array{key: string, price: float}>
     */
    public static function columns(array $planCoverages): array
    {
        $columns = [];

        foreach ($planCoverages as $index => $coverage) {
            if (! is_array($coverage)) {
                continue;
            }

            $key = self::coverageKey($coverage, $index);
            $price = $coverage['price'] ?? null;

            if ($key === null || ! is_numeric($price)) {
                continue;
            }

            $columns[$key] = [
                'key' => $key,
                'price' => (float) $price,
            ];
        }

        $columns = array_values($columns);

        usort($columns, static fn (array $a, array $b): int => $a['price'] <=> $b['price']);

        return $columns;
    }

    /**
     * Reescribe las celdas de una fila para que haya exactamente una por
     * cobertura vigente, conservando lo ya cargado.
     *
     * Una celda cuya cobertura desapareció se descarta; una cobertura nueva
     * entra vacía. Nunca se pisa un valor existente.
     *
     * @param  list<array{key: string, price: float}>  $columns
     * @param  array<array-key, array<string, mixed>>  $cells
     * @return list<array{coverage_key: string, coverage_price: float, ...}>
     */
    public static function syncCells(array $columns, mixed $cells, string $valueField): array
    {
        $existing = [];

        if (is_array($cells)) {
            foreach ($cells as $cell) {
                if (! is_array($cell)) {
                    continue;
                }

                $key = $cell['coverage_key'] ?? null;

                if (! is_string($key) || $key === '') {
                    continue;
                }

                $existing[$key] = $cell;
            }
        }

        $synced = [];

        foreach ($columns as $column) {
            $previous = $existing[$column['key']] ?? [];

            $synced[] = [
                'coverage_key' => $column['key'],
                'coverage_price' => $column['price'],
                $valueField => array_key_exists($valueField, $previous) ? $previous[$valueField] : null,
            ];
        }

        return $synced;
    }

    /**
     * Aplica syncCells() a todas las filas de una matriz.
     *
     * @param  list<array{key: string, price: float}>  $columns
     * @return array<array-key, array<string, mixed>>
     */
    public static function syncRows(array $columns, mixed $rows, string $cellsField, string $valueField): array
    {
        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as $rowKey => $row) {
            if (! is_array($row)) {
                continue;
            }

            $row[$cellsField] = self::syncCells($columns, $row[$cellsField] ?? [], $valueField);
            $rows[$rowKey] = $row;
        }

        return $rows;
    }

    /**
     * Etiqueta de una columna tal como se muestra sobre la casilla.
     */
    public static function columnLabel(float $price): string
    {
        return 'US $'.number_format($price, 2);
    }

    /**
     * La clave de una cobertura ya guardada se deriva de su id, para que al
     * reeditar el plan las celdas vuelvan a emparejarse con su columna.
     */
    public static function keyForPersistedCoverage(int $coverageId): string
    {
        return 'cov-'.$coverageId;
    }

    /**
     * @param  array<string, mixed>  $coverage
     */
    private static function coverageKey(array $coverage, mixed $index): ?string
    {
        $key = $coverage['coverage_key'] ?? null;

        if (is_string($key) && $key !== '') {
            return $key;
        }

        // Repetidor recién cargado desde la base: todavía no pasó por el
        // formulario, así que la clave se deriva del id persistido.
        if (filled($coverage['id'] ?? null)) {
            return self::keyForPersistedCoverage((int) $coverage['id']);
        }

        return is_string($index) && $index !== '' ? $index : null;
    }
}
