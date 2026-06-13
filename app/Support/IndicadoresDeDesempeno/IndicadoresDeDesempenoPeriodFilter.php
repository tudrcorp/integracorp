<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use Illuminate\Database\Eloquent\Builder;

final class IndicadoresDeDesempenoPeriodFilter
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $column, ?int $year, ?string $from, ?string $to): Builder
    {
        if ($from !== null && $to !== null) {
            return $query
                ->whereDate($column, '>=', $from)
                ->whereDate($column, '<=', $to);
        }

        if ($year !== null) {
            return $query->whereYear($column, $year);
        }

        return $query;
    }
}
