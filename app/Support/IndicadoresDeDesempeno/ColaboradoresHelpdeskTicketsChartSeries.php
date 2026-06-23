<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Models\HelpDesk;

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
}
