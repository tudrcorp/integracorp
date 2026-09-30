<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Conteos de encabezado de un listado en una sola consulta agregada.
 *
 * Recibe la consulta ya acotada de la tabla (proveedor, ATENMEDI, scopes globales) para que
 * los números cuadren con lo que el usuario ve, sin los filtros que él mismo aplique.
 */
final class OperationsListHeaderCounts
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, array{0: string, 1?: list<mixed>}>  $conditions  clave => [condición SQL, bindings]
     * @param  array<string, string>  $distinct  clave => columna calificada para COUNT(DISTINCT …)
     * @return array<string, int> siempre incluye «total»
     */
    public static function aggregate(Builder $query, array $conditions = [], array $distinct = []): array
    {
        $columns = ['COUNT(*) AS total'];
        $bindings = [];

        foreach ($conditions as $key => $condition) {
            $columns[] = "SUM(CASE WHEN {$condition[0]} THEN 1 ELSE 0 END) AS ".self::alias($key);
            array_push($bindings, ...($condition[1] ?? []));
        }

        foreach ($distinct as $key => $column) {
            $columns[] = "COUNT(DISTINCT {$column}) AS ".self::alias($key);
        }

        $row = $query->clone()
            ->withoutEagerLoads()
            ->reorder()
            ->toBase()
            ->selectRaw(implode(', ', $columns), $bindings)
            ->first();

        $result = ['total' => (int) ($row->total ?? 0)];

        foreach ([...array_keys($conditions), ...array_keys($distinct)] as $key) {
            $result[$key] = (int) ($row->{self::alias($key)} ?? 0);
        }

        return $result;
    }

    public static function startOfToday(): string
    {
        return Carbon::now((string) config('app.timezone'))->startOfDay()->toDateTimeString();
    }

    public static function startOfMonth(): string
    {
        return Carbon::now((string) config('app.timezone'))->startOfMonth()->toDateTimeString();
    }

    private static function alias(string $key): string
    {
        return 'c_'.preg_replace('/[^a-z0-9_]/i', '_', $key);
    }
}
