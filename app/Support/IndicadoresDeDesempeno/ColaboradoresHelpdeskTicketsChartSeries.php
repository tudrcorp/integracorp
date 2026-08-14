<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Models\HelpDesk;
use Illuminate\Database\Eloquent\Builder;

final class ColaboradoresHelpdeskTicketsChartSeries
{
    /**
     * @return array{labels: list<string>, totals: list<int>}
     */
    public static function totalsByColaborador(?int $year = null, ?string $from = null, ?string $to = null): array
    {
        $labels = SupplierObservationsChartSeries::collaboratorLabels($year, $from, $to);
        $totals = [];

        foreach ($labels as $collaboratorName) {
            $query = HelpDesk::query()->where('created_by', $collaboratorName);

            IndicadoresDeDesempenoPeriodFilter::apply($query, 'created_at', $year, $from, $to);

            $totals[] = (int) $query->count();
        }

        return [
            'labels' => $labels,
            'totals' => $totals,
        ];
    }

    /**
     * @return array{labels: list<string>, totals: list<int>}
     */
    public static function totalsByMonth(int $year, ?string $collaborator = null): array
    {
        $aggregates = self::baseQuery($collaborator)
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as bucket, COUNT(*) as total')
            ->groupByRaw('MONTH(created_at)')
            ->get();

        $counts = [];

        foreach ($aggregates as $row) {
            $counts[(int) $row->bucket] = (int) $row->total;
        }

        return [
            'labels' => IndicadoresDeDesempenoTimeBuckets::monthLabels(),
            'totals' => IndicadoresDeDesempenoTimeBuckets::fillMonthlyTotals($counts),
        ];
    }

    /**
     * @return array{labels: list<string>, totals: list<int>}
     */
    public static function totalsByWeek(int $year, int $month, ?string $collaborator = null): array
    {
        $aggregates = self::baseQuery($collaborator)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('FLOOR((DAY(created_at) - 1) / 7) + 1 as bucket, COUNT(*) as total')
            ->groupByRaw('FLOOR((DAY(created_at) - 1) / 7) + 1')
            ->get();

        $counts = [];

        foreach ($aggregates as $row) {
            $counts[(int) $row->bucket] = (int) $row->total;
        }

        return [
            'labels' => IndicadoresDeDesempenoTimeBuckets::weekLabels($year, $month),
            'totals' => IndicadoresDeDesempenoTimeBuckets::fillWeeklyTotals($year, $month, $counts),
        ];
    }

    /**
     * @return array{labels: list<string>, totals: list<int>}
     */
    public static function totalsByCollaboratorForWeek(
        int $year,
        int $month,
        int $week,
        ?string $collaborator = null,
    ): array {
        $range = IndicadoresDeDesempenoTimeBuckets::weekDateRange($year, $month, $week);

        $aggregates = self::baseQuery($collaborator)
            ->whereDate('created_at', '>=', $range['from'])
            ->whereDate('created_at', '<=', $range['to'])
            ->whereNotNull('created_by')
            ->whereRaw("NULLIF(TRIM(created_by), '') IS NOT NULL")
            ->selectRaw('TRIM(created_by) as collaborator, COUNT(*) as total')
            ->groupByRaw('TRIM(created_by)')
            ->orderByDesc('total')
            ->orderBy('collaborator')
            ->get();

        $labels = [];
        $totals = [];

        foreach ($aggregates as $row) {
            $labels[] = (string) $row->collaborator;
            $totals[] = (int) $row->total;
        }

        if ($collaborator !== null && $labels === []) {
            return [
                'labels' => [$collaborator],
                'totals' => [0],
            ];
        }

        return [
            'labels' => $labels,
            'totals' => $totals,
        ];
    }

    /**
     * @return Builder<HelpDesk>
     */
    private static function baseQuery(?string $collaborator): Builder
    {
        $query = HelpDesk::query();

        if ($collaborator !== null) {
            $query->where('created_by', $collaborator);
        }

        return $query;
    }
}
