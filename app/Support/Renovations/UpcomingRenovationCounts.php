<?php

declare(strict_types=1);

namespace App\Support\Renovations;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cuántas renovaciones caen en los próximos 15, 30 y 45 días (por `date_renewal`).
 * Solo cantidades, sin montos. Los plazos son acumulados: el de 30 incluye el de 15.
 */
final class UpcomingRenovationCounts
{
    /** @var list<int> */
    public const WINDOWS = [15, 30, 45];

    /**
     * Recibe la consulta filtrada de la tabla (pestaña, filtros y búsqueda) y la
     * resuelve en una sola consulta agregada, sin cargar filas.
     *
     * @return array<int, int> Días => cantidad.
     */
    public static function forQuery(Builder $query, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $column = $query->getModel()->getTable().'.date_renewal';

        $query = (clone $query)->reorder();
        $query->setEagerLoads([]);
        $query->getQuery()->columns = null;
        $query->getQuery()->limit = null;
        $query->getQuery()->offset = null;

        foreach (self::WINDOWS as $days) {
            $query->selectRaw(
                "SUM(CASE WHEN {$column} BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_{$days}",
                [$today->toDateString(), $today->addDays($days)->toDateString()],
            );
        }

        $row = $query->toBase()->first();

        $counts = [];

        foreach (self::WINDOWS as $days) {
            $counts[$days] = (int) ($row->{"due_{$days}"} ?? 0);
        }

        return $counts;
    }
}
