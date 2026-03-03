<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TotalSaleForAgent extends ChartWidget
{
    protected ?string $heading = 'Total de ventas por agente';

    public ?string $filter = 'year';

    protected function getFilters(): ?array
    {
        return [
            'year' => 'Este año',
            'month' => 'Este mes',
            'week' => 'Esta semana',
            'last_5_days' => 'Últimos 5 días',
        ];
    }

    protected function getData(): array
    {
        $now = Carbon::now();

        $salesQuery = Sale::query()
            ->select([
                'agents.name as label',
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
            ])
            ->join('agents', 'agents.id', '=', 'sales.agent_id');

        if ($this->filter === 'month') {
            $salesQuery
                ->whereMonth('sales.created_at', $now->month)
                ->whereYear('sales.created_at', $now->year);
        } elseif ($this->filter === 'week') {
            $salesQuery->whereBetween('sales.created_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ]);
        } elseif ($this->filter === 'last_5_days') {
            $salesQuery->whereBetween('sales.created_at', [
                $now->copy()->subDays(4)->startOfDay(),
                $now->copy()->endOfDay(),
            ]);
        } elseif ($this->filter === 'year') {
            $salesQuery->whereYear('sales.created_at', $now->year);
        }

        $salesData = $salesQuery
            ->groupBy('agents.name')
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->get();

        $labels = $salesData->pluck('label')->toArray();
        $values = $salesData->pluck('total')->map(fn ($v) => (float) $v)->toArray();

        $backgroundColors = [];
        foreach ($labels as $_) {
            $backgroundColors[] = sprintf(
                '#%02x%02x%02x',
                random_int(0, 255),
                random_int(0, 255),
                random_int(0, 255),
            );
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total de ventas (US$)',
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => 'rgba(0,0,0,0.1)',
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
