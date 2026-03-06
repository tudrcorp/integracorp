<?php

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Models\ProspectAgent;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopRegisterProspect extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Top 10 colaboradores por prospectos registrados';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $topColaboradores = ProspectAgent::query()
            ->select([
                'created_by as label',
                DB::raw('COUNT(*) as total'),
            ])
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->groupBy('created_by')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $labels = $topColaboradores->pluck('label')->map(fn (?string $name): string => $name ?? 'Sin nombre')->toArray();
        $data = $topColaboradores->pluck('total')->map(fn (mixed $v): int => (int) $v)->toArray();

        $colors = [
            '#38bdf8',
            '#0ea5e9',
            '#0284c7',
            '#0369a1',
            '#075985',
            '#0c4a6e',
            '#7dd3fc',
            '#06b6d4',
            '#0891b2',
            '#0e7490',
        ];
        $backgroundColors = [];
        foreach ($data as $index => $value) {
            $backgroundColors[] = $colors[$index % count($colors)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Prospectos registrados',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => 'rgba(0,0,0,0.08)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            barPercentage: 0.6,
            categoryPercentage: 0.8,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => context.parsed.y + ' prospecto(s) registrado(s)'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: {
                        display: true,
                        color: 'rgba(156, 163, 175, 0.25)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: true,
                        color: 'rgba(156, 163, 175, 0.25)',
                        drawBorder: false
                    }
                }
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
