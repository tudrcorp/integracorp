<?php

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AffiliationCorporatesRankingQuery
{
    /**
     * Agencias con afiliaciones corporativas. Cuenta primero en affiliation_corporates
     * y luego une agencies por código.
     */
    public static function agencies(?int $year = null, ?int $month = null): Builder
    {
        $affiliationCounts = AffiliationCorporate::query()
            ->select([
                'code_agency',
                DB::raw('COUNT(*) as total_affiliations'),
            ])
            ->whereNotNull('code_agency')
            ->where('code_agency', '!=', '')
            ->tap(fn (Builder $query): Builder => self::constrainActive($query))
            ->tap(fn (Builder $query): Builder => self::applyPeriod($query, $year, $month))
            ->groupBy('code_agency');

        return Agency::query()
            ->select([
                'agencies.id',
                'agencies.code',
                'agencies.name_corporative',
                'agencies.agency_type_id',
                'affiliation_counts.total_affiliations',
            ])
            ->joinSub($affiliationCounts, 'affiliation_counts', function ($join): void {
                $join->on('agencies.code', '=', 'affiliation_counts.code_agency');
            });
    }

    /**
     * Agentes con afiliaciones corporativas. Si hay código de agencia, filtra
     * afiliaciones antes del GROUP BY.
     */
    public static function agents(?string $agencyCode = null, ?int $year = null, ?int $month = null): Builder
    {
        $affiliationCounts = AffiliationCorporate::query()
            ->select([
                'agent_id',
                DB::raw('COUNT(*) as total_affiliations'),
            ])
            ->whereNotNull('agent_id')
            ->where('agent_id', '!=', '')
            ->when(
                filled($agencyCode),
                fn (Builder $query): Builder => $query->where('code_agency', $agencyCode),
            )
            ->tap(fn (Builder $query): Builder => self::constrainActive($query))
            ->tap(fn (Builder $query): Builder => self::applyPeriod($query, $year, $month))
            ->groupBy('agent_id');

        return Agent::query()
            ->select([
                'agents.id',
                'agents.name',
                'agents.code_agent',
                'agents.owner_code',
                'agents.agent_type_id',
                'affiliation_counts.total_affiliations',
            ])
            ->joinSub($affiliationCounts, 'affiliation_counts', function ($join): void {
                $join->on('agents.id', '=', 'affiliation_counts.agent_id');
            });
    }

    public static function constrainActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVA');
    }

    public static function constrainWithoutAgent(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('agent_id')->orWhere('agent_id', '');
        });
    }

    protected static function applyPeriod(Builder $query, ?int $year, ?int $month): Builder
    {
        if ($year !== null) {
            $query->whereYear('created_at', $year);
        }

        if ($month !== null) {
            $query->whereMonth('created_at', $month);
        }

        return $query;
    }
}
