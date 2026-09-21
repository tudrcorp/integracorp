<?php

declare(strict_types=1);

namespace App\Support\Renovations;

use App\Jobs\PrepareAffiliationCorporateRenovations;
use App\Jobs\PrepareAffiliationRenovations;
use App\Models\AffiliationCorporateRenovationHistory;
use App\Models\AffiliationRenovationHistory;
use App\Models\Renovation;
use App\Models\RenovationCorporate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class RenovationKpiCalculator
{
    public static function individual(?Carbon $now = null): RenovationKpiSnapshot
    {
        $now = ($now ?? Carbon::now())->copy();

        return self::snapshot(
            isCorporate: false,
            now: $now,
            historyQuery: AffiliationRenovationHistory::query(),
            distinctColumn: 'affiliation_id',
            queueQuery: Renovation::query(),
            periodStatus: PrepareAffiliationRenovations::STATUS_RENOVATION_PERIOD,
        );
    }

    public static function corporate(?Carbon $now = null): RenovationKpiSnapshot
    {
        $now = ($now ?? Carbon::now())->copy();

        return self::snapshot(
            isCorporate: true,
            now: $now,
            historyQuery: AffiliationCorporateRenovationHistory::query(),
            distinctColumn: 'affiliation_corporate_id',
            queueQuery: RenovationCorporate::query(),
            periodStatus: PrepareAffiliationCorporateRenovations::STATUS_RENOVATION_PERIOD,
        );
    }

    public static function retentionRate(int $acceptedCount, int $overdueOpenCount): ?float
    {
        $denominator = $acceptedCount + $overdueOpenCount;

        if ($denominator === 0) {
            return null;
        }

        return $acceptedCount / $denominator;
    }

    public static function churnRate(int $acceptedCount, int $overdueOpenCount): ?float
    {
        $retention = self::retentionRate($acceptedCount, $overdueOpenCount);

        if ($retention === null) {
            return null;
        }

        return 1 - $retention;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $historyQuery
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $queueQuery
     */
    private static function snapshot(
        bool $isCorporate,
        Carbon $now,
        Builder $historyQuery,
        string $distinctColumn,
        Builder $queueQuery,
        string $periodStatus,
    ): RenovationKpiSnapshot {
        $from = $now->copy()->startOfMonth();
        $to = $from->copy()->addMonth();

        $entityColumn = $distinctColumn === 'affiliation_corporate_id'
            ? 'affiliation_corporate_id'
            : 'affiliation_id';

        $accepted = $historyQuery
            ->clone()
            ->where('accepted_at', '>=', $from)
            ->where('accepted_at', '<', $to)
            ->toBase()
            ->selectRaw("COUNT(DISTINCT {$entityColumn}) as accepted_count")
            ->selectRaw('COALESCE(SUM(subtotal_anual), 0) as retained_premium')
            ->selectRaw('AVG(remaining_days_at_accept) as avg_anticipation_days')
            ->first();

        $acceptedCount = (int) ($accepted?->accepted_count ?? 0);
        $retainedPremium = (float) ($accepted?->retained_premium ?? 0);
        $avgAnticipation = $accepted?->avg_anticipation_days !== null
            ? (float) $accepted->avg_anticipation_days
            : null;

        $overdueOpenCount = (int) $queueQuery
            ->clone()
            ->where('remaining_days', '<', 0)
            ->count();

        $inWindowOpenCount = (int) $queueQuery
            ->clone()
            ->where('status', $periodStatus)
            ->where('remaining_days', '>=', 0)
            ->count();

        $acceptorRows = $historyQuery
            ->clone()
            ->where('accepted_at', '>=', $from)
            ->where('accepted_at', '<', $to)
            ->toBase()
            ->selectRaw('accepted_by')
            ->selectRaw("COUNT(DISTINCT {$entityColumn}) as accepted_count")
            ->selectRaw('COALESCE(SUM(subtotal_anual), 0) as retained_premium')
            ->selectRaw('AVG(remaining_days_at_accept) as avg_anticipation_days')
            ->groupBy('accepted_by')
            ->orderByDesc('accepted_count')
            ->orderBy('accepted_by')
            ->get();

        $acceptors = $acceptorRows
            ->map(function (object $row): RenovationKpiAcceptorRow {
                $name = trim((string) ($row->accepted_by ?? ''));

                return new RenovationKpiAcceptorRow(
                    acceptedBy: $name !== '' ? $name : 'Sin identificar',
                    acceptedCount: (int) ($row->accepted_count ?? 0),
                    retainedPremium: (float) ($row->retained_premium ?? 0),
                    avgAnticipationDays: $row->avg_anticipation_days !== null
                        ? (float) $row->avg_anticipation_days
                        : null,
                );
            })
            ->values()
            ->all();

        return new RenovationKpiSnapshot(
            periodLabel: ucfirst($now->translatedFormat('F Y')),
            isCorporate: $isCorporate,
            acceptedCount: $acceptedCount,
            retainedPremium: $retainedPremium,
            avgAnticipationDays: $avgAnticipation,
            overdueOpenCount: $overdueOpenCount,
            inWindowOpenCount: $inWindowOpenCount,
            retentionRate: self::retentionRate($acceptedCount, $overdueOpenCount),
            churnRate: self::churnRate($acceptedCount, $overdueOpenCount),
            acceptors: $acceptors,
        );
    }
}
