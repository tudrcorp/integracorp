<?php

namespace App\Support\AgentActivity;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class AgentActivityQuery
{
    /**
     * Agrega al query de agentes:
     * - last_sale_at (última venta)
     * - last_interaction_at (máximo entre ventas y cotizaciones)
     *
     * No requiere columnas físicas en la tabla `agents`.
     */
    public static function applyToAgentsQuery(Builder $query, ?CarbonInterface $until = null): Builder
    {
        $lastSales = DB::table('sales')
            ->select('agent_id', DB::raw('MAX(created_at) as last_sale_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        $lastIndividualQuotes = DB::table('individual_quotes')
            ->select('agent_id', DB::raw('MAX(created_at) as last_individual_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        $lastCorporateQuotes = DB::table('corporate_quotes')
            ->select('agent_id', DB::raw('MAX(created_at) as last_corporate_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        $lastCorporateQuoteRequests = DB::table('corporate_quote_requests')
            ->select('agent_id', DB::raw('MAX(created_at) as last_corporate_request_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        return $query
            // Asegura que el modelo tenga su PK (agents.id) para Filament.
            ->addSelect('agents.*')
            ->leftJoinSub($lastSales, 'ls', fn ($j) => $j->on('ls.agent_id', '=', 'agents.id'))
            ->leftJoinSub($lastIndividualQuotes, 'liq', fn ($j) => $j->on('liq.agent_id', '=', 'agents.id'))
            ->leftJoinSub($lastCorporateQuotes, 'lcq', fn ($j) => $j->on('lcq.agent_id', '=', 'agents.id'))
            ->leftJoinSub($lastCorporateQuoteRequests, 'lcr', fn ($j) => $j->on('lcr.agent_id', '=', 'agents.id'))
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
     * Variante para Query Builder (DB::table('agents')).
     */
    public static function applyToAgentsTableQuery(QueryBuilder $query, ?CarbonInterface $until = null): QueryBuilder
    {
        $lastSales = DB::table('sales')
            ->select('agent_id', DB::raw('MAX(created_at) as last_sale_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        $lastIndividualQuotes = DB::table('individual_quotes')
            ->select('agent_id', DB::raw('MAX(created_at) as last_individual_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        $lastCorporateQuotes = DB::table('corporate_quotes')
            ->select('agent_id', DB::raw('MAX(created_at) as last_corporate_quote_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        $lastCorporateQuoteRequests = DB::table('corporate_quote_requests')
            ->select('agent_id', DB::raw('MAX(created_at) as last_corporate_request_at'))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->groupBy('agent_id');

        return $query
            ->addSelect('agents.*')
            ->leftJoinSub($lastSales, 'ls', fn ($j) => $j->on('ls.agent_id', '=', 'agents.id'))
            ->leftJoinSub($lastIndividualQuotes, 'liq', fn ($j) => $j->on('liq.agent_id', '=', 'agents.id'))
            ->leftJoinSub($lastCorporateQuotes, 'lcq', fn ($j) => $j->on('lcq.agent_id', '=', 'agents.id'))
            ->leftJoinSub($lastCorporateQuoteRequests, 'lcr', fn ($j) => $j->on('lcr.agent_id', '=', 'agents.id'))
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
