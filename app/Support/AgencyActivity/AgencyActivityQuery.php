<?php

namespace App\Support\AgencyActivity;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class AgencyActivityQuery
{
    /**
     * Agrega al query de agencias:
     * - last_sale_at (última venta)
     * - last_interaction_at (máximo entre ventas y cotizaciones)
     *
     * No requiere columnas físicas en la tabla `agencies`.
     */
    public static function applyToAgenciesQuery(Builder $query, ?CarbonInterface $until = null): Builder
    {
        $lastSales = DB::table('sales')
            ->select('code_agency', DB::raw('MAX(created_at) as last_sale_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        $lastIndividualQuotes = DB::table('individual_quotes')
            ->select('code_agency', DB::raw('MAX(created_at) as last_individual_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        $lastCorporateQuotes = DB::table('corporate_quotes')
            ->select('code_agency', DB::raw('MAX(created_at) as last_corporate_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        $lastCorporateQuoteRequests = DB::table('corporate_quote_requests')
            ->select('code_agency', DB::raw('MAX(created_at) as last_corporate_request_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        return $query
            ->addSelect('agencies.*')
            ->leftJoinSub($lastSales, 'ls', fn ($j) => $j->on('ls.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($lastIndividualQuotes, 'liq', fn ($j) => $j->on('liq.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($lastCorporateQuotes, 'lcq', fn ($j) => $j->on('lcq.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($lastCorporateQuoteRequests, 'lcr', fn ($j) => $j->on('lcr.code_agency', '=', 'agencies.code'))
            ->addSelect([
                'ls.last_sale_at',
                DB::raw("CASE
                    WHEN ls.last_sale_at IS NULL
                        AND liq.last_individual_quote_at IS NULL
                        AND lcq.last_corporate_quote_at IS NULL
                        AND lcr.last_corporate_request_at IS NULL
                    THEN NULL
                    ELSE GREATEST(
                        COALESCE(ls.last_sale_at, '1970-01-01 00:00:00'),
                        COALESCE(liq.last_individual_quote_at, '1970-01-01 00:00:00'),
                        COALESCE(lcq.last_corporate_quote_at, '1970-01-01 00:00:00'),
                        COALESCE(lcr.last_corporate_request_at, '1970-01-01 00:00:00')
                    )
                END as last_interaction_at"),
            ]);
    }

    /**
     * Variante para Query Builder (DB::table('agencies')).
     */
    public static function applyToAgenciesTableQuery(QueryBuilder $query, ?CarbonInterface $until = null): QueryBuilder
    {
        $lastSales = DB::table('sales')
            ->select('code_agency', DB::raw('MAX(created_at) as last_sale_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        $lastIndividualQuotes = DB::table('individual_quotes')
            ->select('code_agency', DB::raw('MAX(created_at) as last_individual_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        $lastCorporateQuotes = DB::table('corporate_quotes')
            ->select('code_agency', DB::raw('MAX(created_at) as last_corporate_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        $lastCorporateQuoteRequests = DB::table('corporate_quote_requests')
            ->select('code_agency', DB::raw('MAX(created_at) as last_corporate_request_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('code_agency');

        return $query
            ->addSelect('agencies.*')
            ->leftJoinSub($lastSales, 'ls', fn ($j) => $j->on('ls.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($lastIndividualQuotes, 'liq', fn ($j) => $j->on('liq.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($lastCorporateQuotes, 'lcq', fn ($j) => $j->on('lcq.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($lastCorporateQuoteRequests, 'lcr', fn ($j) => $j->on('lcr.code_agency', '=', 'agencies.code'))
            ->addSelect([
                'ls.last_sale_at',
                DB::raw("CASE
                    WHEN ls.last_sale_at IS NULL
                        AND liq.last_individual_quote_at IS NULL
                        AND lcq.last_corporate_quote_at IS NULL
                        AND lcr.last_corporate_request_at IS NULL
                    THEN NULL
                    ELSE GREATEST(
                        COALESCE(ls.last_sale_at, '1970-01-01 00:00:00'),
                        COALESCE(liq.last_individual_quote_at, '1970-01-01 00:00:00'),
                        COALESCE(lcq.last_corporate_quote_at, '1970-01-01 00:00:00'),
                        COALESCE(lcr.last_corporate_request_at, '1970-01-01 00:00:00')
                    )
                END as last_interaction_at"),
            ]);
    }
}
