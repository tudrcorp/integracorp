<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TotalSaleMonthlyNowVsLastAgent extends ChartWidget
{
    protected ?string $heading = 'Comparativo de Ventas por Agente. Mes Actual Vs Mes Anterior';

    public ?string $filter = '1';

    protected function getFilters(): ?array
    {
        return [
            '1' => 'Hace 1 mes',
            '2' => 'Hace 2 meses',
            '3' => 'Hace 3 meses',
            '6' => 'Hace 6 meses',
            '12' => 'Hace 12 meses',
        ];
    }

    protected function getData(): array
    {
        $now = Carbon::now();

        $currentMonth = $now->month;
        $currentYear = $now->year;

        $monthsBack = (int) ($this->filter ?? 1);
        if ($monthsBack < 1) {
            $monthsBack = 1;
        }

        $previous = $now->copy()->subMonths($monthsBack);
        $previousMonth = $previous->month;
        $previousYear = $previous->year;

        $currentMonthSales = Sale::query()
            ->select([
                'agents.id as agent_id',
                'agents.name as label',
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
            ])
            ->join('agents', 'agents.id', '=', 'sales.agent_id')
            ->whereMonth('sales.created_at', $currentMonth)
            ->whereYear('sales.created_at', $currentYear)
            ->groupBy('agents.id', 'agents.name')
            ->get()
            ->keyBy('agent_id');

        $previousMonthSales = Sale::query()
            ->select([
                'agents.id as agent_id',
                'agents.name as label',
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
            ])
            ->join('agents', 'agents.id', '=', 'sales.agent_id')
            ->whereMonth('sales.created_at', $previousMonth)
            ->whereYear('sales.created_at', $previousYear)
            ->groupBy('agents.id', 'agents.name')
            ->get()
            ->keyBy('agent_id');

        $agents = collect()
            ->merge($currentMonthSales)
            ->merge($previousMonthSales)
            ->keys()
            ->map(function ($agentId) use ($currentMonthSales, $previousMonthSales) {
                $current = $currentMonthSales->get($agentId);
                $previous = $previousMonthSales->get($agentId);

                $currentTotal = $current ? (float) $current->total : 0.0;
                $previousTotal = $previous ? (float) $previous->total : 0.0;

                return [
                    'label' => $current?->label ?? $previous?->label ?? 'Sin nombre',
                    'current' => $currentTotal,
                    'previous' => $previousTotal,
                    'total' => $currentTotal + $previousTotal,
                ];
            })
            ->filter(fn (array $agent): bool => $agent['total'] > 0)
            ->sortByDesc('total')
            ->values();

        $labels = $agents
            ->map(function (array $agent): string {
                $totalFormatted = number_format($agent['total'], 2, '.', ',');

                return "{$agent['label']} (US$ {$totalFormatted})";
            })
            ->toArray();
        $currentData = $agents->pluck('current')->toArray();
        $previousData = $agents->pluck('previous')->toArray();

        $currentMonthLabel = ucfirst($now->copy()->locale(app()->getLocale())->translatedFormat('F Y'));
        $previousMonthLabel = ucfirst($previous->copy()->locale(app()->getLocale())->translatedFormat('F Y'));

        return [
            'datasets' => [
                [
                    'label' => "Mes actual ({$currentMonthLabel})",
                    'data' => $currentData,
                    'backgroundColor' => 'rgba(34,197,94,0.7)', // Verde
                    'borderColor' => 'rgba(22,163,74,1)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'label' => "Mes anterior ({$previousMonthLabel})",
                    'data' => $previousData,
                    'backgroundColor' => 'rgba(239,68,68,0.7)', // Rojo
                    'borderColor' => 'rgba(220,38,38,1)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
