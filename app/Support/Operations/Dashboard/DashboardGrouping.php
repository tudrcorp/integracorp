<?php

declare(strict_types=1);

namespace App\Support\Operations\Dashboard;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Agrupación portable para los tableros (Operaciones, Proveedor y Métricas).
 *
 * El motor de producción, con ONLY_FULL_GROUP_BY, rechaza agrupar por una
 * expresión (`GROUP BY UPPER(TRIM(status))`) y también, en varios casos, por su
 * alias, aunque el MySQL local acepte ambos. Aquí las claves se calculan en una
 * subconsulta y el GROUP BY de afuera usa columnas simples de esa subconsulta,
 * que cualquier motor acepta.
 *
 * Uso: `DashboardGrouping::by($query, ['grp_label' => 'UPPER(x)'], ['amount' => 'sales.total_amount'])
 *     ->selectRaw('SUM(g.amount) as total')->pluck('total', 'grp_label')`.
 */
final class DashboardGrouping
{
    public const ALIAS = 'g';

    /**
     * @param  array<string, string>  $keys  alias => expresión SQL por la que se agrupa
     * @param  array<string, string>  $columns  alias => expresión SQL que los agregados de afuera necesitan (`g.alias`)
     */
    public static function by(Builder|EloquentBuilder $query, array $keys, array $columns = []): Builder
    {
        $inner = clone $query;
        $base = $inner instanceof EloquentBuilder ? $inner->getQuery() : $inner;
        $base->columns = null;
        $base->bindings['select'] = [];

        foreach ($keys + $columns as $alias => $sql) {
            $inner->selectRaw($sql.' as '.$alias);
        }

        $qualified = array_map(static fn (string $alias): string => self::ALIAS.'.'.$alias, array_keys($keys));

        return $base->newQuery()
            ->fromSub($inner, self::ALIAS)
            ->select($qualified)
            ->groupBy($qualified);
    }
}
