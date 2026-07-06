<?php

namespace App\Support\IndividualQuotes;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\IndividualQuote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IndividualQuotesRankingQuery
{
    /**
     * Agencias con cotizaciones sin agente. Cuenta primero en individual_quotes
     * (tabla más selectiva) y luego une agencies por código.
     */
    public static function agencies(): Builder
    {
        $quoteCounts = IndividualQuote::query()
            ->select([
                'code_agency',
                DB::raw('COUNT(*) as total_quotes'),
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('agent_id')
                    ->orWhere('agent_id', '');
            })
            ->whereNotNull('code_agency')
            ->where('code_agency', '!=', '')
            ->groupBy('code_agency');

        return Agency::query()
            ->select([
                'agencies.id',
                'agencies.code',
                'agencies.name_corporative',
                'agencies.agency_type_id',
                'quote_counts.total_quotes',
            ])
            ->joinSub($quoteCounts, 'quote_counts', function ($join): void {
                $join->on('agencies.code', '=', 'quote_counts.code_agency');
            });
    }

    /**
     * Agentes con cotizaciones. Si hay código de agencia, filtra quotes antes
     * del GROUP BY para aprovechar el índice en owner_code.
     */
    public static function agents(?string $agencyCode = null): Builder
    {
        $quoteCounts = IndividualQuote::query()
            ->select([
                'agent_id',
                DB::raw('COUNT(*) as total_quotes'),
            ])
            ->whereNotNull('agent_id')
            ->where('agent_id', '!=', '')
            ->when(
                filled($agencyCode),
                fn (Builder $query): Builder => $query->where('owner_code', $agencyCode),
            )
            ->groupBy('agent_id');

        return Agent::query()
            ->select([
                'agents.id',
                'agents.name',
                'agents.code_agent',
                'agents.owner_code',
                'agents.agent_type_id',
                'quote_counts.total_quotes',
            ])
            ->joinSub($quoteCounts, 'quote_counts', function ($join): void {
                $join->on('agents.id', '=', 'quote_counts.agent_id');
            });
    }
}
