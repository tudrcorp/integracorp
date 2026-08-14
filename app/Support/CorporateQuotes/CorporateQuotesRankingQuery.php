<?php

namespace App\Support\CorporateQuotes;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\CorporateQuote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CorporateQuotesRankingQuery
{
    /**
     * Agencias con cotizaciones corporativas. Cuenta primero en corporate_quotes
     * y luego une agencies por código.
     */
    public static function agencies(?int $year = null, ?int $month = null): Builder
    {
        $quoteCounts = CorporateQuote::query()
            ->select([
                'code_agency',
                DB::raw('COUNT(*) as total_quotes'),
            ])
            ->whereNotNull('code_agency')
            ->where('code_agency', '!=', '')
            ->tap(fn (Builder $query): Builder => self::applyPeriod($query, $year, $month))
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
     * Agentes con cotizaciones corporativas. Si hay código de agencia, filtra
     * cotizaciones antes del GROUP BY.
     */
    public static function agents(?string $agencyCode = null, ?int $year = null, ?int $month = null): Builder
    {
        $quoteCounts = CorporateQuote::query()
            ->select([
                'agent_id',
                DB::raw('COUNT(*) as total_quotes'),
            ])
            ->whereNotNull('agent_id')
            ->where('agent_id', '!=', '')
            ->when(
                filled($agencyCode),
                fn (Builder $query): Builder => $query->where('code_agency', $agencyCode),
            )
            ->tap(fn (Builder $query): Builder => self::applyPeriod($query, $year, $month))
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

    protected static function applyPeriod(Builder $query, ?int $year, ?int $month): Builder
    {
        if ($year === null) {
            return $query;
        }

        $query->whereYear('created_at', $year);

        if ($month !== null) {
            $query->whereMonth('created_at', $month);
        }

        return $query;
    }
}
