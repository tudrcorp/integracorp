<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Foto exacta de columnas concretas de filas concretas: la base de los puntos de
 * restauración (unificación de grupo familiar, corrección de vigencia…).
 *
 * Se lee con el query builder (valores crudos de la base, sin casts) para que la foto de
 * «después» y la lectura al revertir sean comparables, y se restituye igual, sin pasar
 * por mutadores ni observers. Nada fuera de las columnas declaradas se toca.
 */
final class TableRowSnapshot
{
    /**
     * @param  array<string, list<string>>  $columnsByTable
     * @param  array<string, list<int>>  $idsByTable
     * @return array<string, array<int, array<string, string|null>>>
     */
    public static function capture(array $columnsByTable, array $idsByTable): array
    {
        $snapshot = [];

        foreach ($columnsByTable as $table => $columns) {
            $ids = array_values(array_unique(array_map(intval(...), $idsByTable[$table] ?? [])));
            $snapshot[$table] = [];

            if ($ids === []) {
                continue;
            }

            sort($ids);

            foreach (DB::table($table)->whereIn('id', $ids)->orderBy('id')->get(['id', ...$columns]) as $row) {
                $values = [];

                foreach ($columns as $column) {
                    $values[$column] = self::normalize($row->{$column} ?? null);
                }

                $snapshot[$table][(int) $row->id] = $values;
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<string, list<string>>  $columnsByTable
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     * @return array<string, list<int>>
     */
    public static function idsOf(array $columnsByTable, array $snapshot): array
    {
        $ids = [];

        foreach (array_keys($columnsByTable) as $table) {
            $ids[$table] = array_map(intval(...), array_keys($snapshot[$table] ?? []));
        }

        return $ids;
    }

    /**
     * Diferencias entre una foto esperada y lo que hay hoy en la base.
     *
     * @param  array<string, list<string>>  $columnsByTable
     * @param  array<string, array<int|string, array<string, string|null>>>  $expected
     * @param  array<string, array<int|string, array<string, string|null>>>  $current
     * @return list<array{table: string, id: int, column: string|null, expected: string|null, current: string|null}>
     */
    public static function differences(array $columnsByTable, array $expected, array $current): array
    {
        $differences = [];

        foreach (array_keys($columnsByTable) as $table) {
            foreach ($expected[$table] ?? [] as $id => $columns) {
                $id = (int) $id;
                $now = $current[$table][$id] ?? null;

                if ($now === null) {
                    $differences[] = ['table' => $table, 'id' => $id, 'column' => null, 'expected' => null, 'current' => null];

                    continue;
                }

                foreach ($columns as $column => $value) {
                    if (! self::sameValue($value, $now[$column] ?? null)) {
                        $differences[] = [
                            'table' => $table,
                            'id' => $id,
                            'column' => $column,
                            'expected' => $value,
                            'current' => $now[$column] ?? null,
                        ];
                    }
                }
            }
        }

        return $differences;
    }

    /**
     * Restituye la foto: cada fila vuelve a sus valores en las columnas declaradas.
     *
     * @param  array<string, list<string>>  $columnsByTable
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     * @return int Filas restituidas.
     */
    public static function restore(array $columnsByTable, array $snapshot): int
    {
        $restored = 0;

        foreach ($columnsByTable as $table => $columns) {
            foreach ($snapshot[$table] ?? [] as $id => $values) {
                $payload = array_intersect_key($values, array_flip($columns));

                if ($payload === []) {
                    continue;
                }

                DB::table($table)->where('id', (int) $id)->update($payload);
                $restored++;
            }
        }

        return $restored;
    }

    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Compara como texto y, si ambos son numéricos, como número (120 = 120.00).
     */
    public static function sameValue(?string $left, ?string $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if ($left === null || $right === null) {
            return false;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.000001;
        }

        return false;
    }
}
