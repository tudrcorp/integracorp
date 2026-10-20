<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Conteos de los widgets del listado de proveedores en una sola consulta agrupada.
 *
 * Cada widget recibía la consulta filtrada de la tabla y lanzaba un `count()` por tarjeta
 * (unas 35 consultas por render, repetidas en cada búsqueda o filtro). Aquí se agrupa una vez.
 *
 * La consulta de la tabla trae `select suppliers.*` y joins para ordenar: se vacía el select antes
 * de agregar, o MySQL (only_full_group_by) rechaza la consulta.
 */
final class SupplierListStatCounts
{
    /**
     * Totales por estatus de sistema, con la clave normalizada (mayúsculas, sin espacios en los
     * extremos) igual que comparaba MySQL con su collation. La clave `''` agrupa los que no tienen estatus.
     *
     * @param  Builder<\App\Models\Supplier>  $filteredQuery
     * @return array<string, int>
     */
    public static function bySystemStatus(Builder $filteredQuery): array
    {
        $rows = (clone $filteredQuery)
            ->reorder()
            ->toBase()
            ->select([])
            ->selectRaw('suppliers.status_sistema as status_sistema, COUNT(*) as aggregate')
            ->groupBy('suppliers.status_sistema')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $key = self::statusKey($row->status_sistema ?? null);
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * Las cuatro tarjetas de «Total de proveedores» en una sola pasada.
     *
     * @param  Builder<\App\Models\Supplier>  $filteredQuery
     * @return array{total: int, registered_in_month: int, with_acceptance_letter: int, not_agreed: int}
     */
    public static function summary(Builder $filteredQuery, Carbon $monthStart, Carbon $monthEnd): array
    {
        $row = (clone $filteredQuery)
            ->reorder()
            ->toBase()
            ->select([])
            ->selectRaw(
                'COUNT(*) as total,'
                .' COALESCE(SUM(CASE WHEN suppliers.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as registered_in_month,'
                ." COALESCE(SUM(CASE WHEN (suppliers.status_convenio = 'GENERAL' OR suppliers.status_convenio LIKE '%PREFERENCIAL%')"
                ." AND suppliers.carta_acceptance IS NOT NULL AND suppliers.carta_acceptance <> '' THEN 1 ELSE 0 END), 0) as with_acceptance_letter,"
                ." COALESCE(SUM(CASE WHEN suppliers.status_convenio = 'NO CONVENIDO' THEN 1 ELSE 0 END), 0) as not_agreed",
                [$monthStart, $monthEnd],
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'registered_in_month' => (int) ($row->registered_in_month ?? 0),
            'with_acceptance_letter' => (int) ($row->with_acceptance_letter ?? 0),
            'not_agreed' => (int) ($row->not_agreed ?? 0),
        ];
    }

    public static function statusKey(?string $status): string
    {
        return mb_strtoupper(trim((string) $status));
    }
}
