<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Business\Resources\Agents\Pages\ListAgents;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

class TotalForStateAgent extends ChartWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAgents::class;
    }

    public function mount(): void
    {
        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected ?string $maxHeight = '440px';

    protected ?string $heading = 'Agentes por estado';

    protected ?string $description = 'Distribución de agentes registrados por estado. El gráfico respeta los filtros de la tabla.';

    protected int|string|array $columnSpan = 'full';

    /**
     * Paleta de colores distintivos para los estados (orden consistente).
     */
    protected function getStateColors(int $count): array
    {
        $palette = [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
            '#14b8a6', '#a855f7', '#eab308', '#22c55e', '#0ea5e9',
        ];

        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $palette[$i % count($palette)];
        }

        return $colors;
    }

    protected function getData(): array
    {
        $baseQuery = $this->getPageTableQuery();

        $results = DB::table(DB::raw('('.$baseQuery->toSql().') as agents'))
            ->mergeBindings($baseQuery->getQuery())
            ->leftJoin('states', 'agents.state_id', '=', 'states.id')
            ->select(
                DB::raw('COALESCE(states.definition, "Sin estado") as state_name'),
                DB::raw('states.id as state_id'),
                DB::raw('count(agents.id) as total')
            )
            ->groupBy('states.id', 'states.definition')
            ->orderByDesc('total')
            ->get();

        if ($results->isEmpty()) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [[
                    'data' => [100],
                    'backgroundColor' => ['#e5e7eb'],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ]],
            ];
        }

        $totalAgents = $results->sum('total');
        $labels = $results->pluck('state_name')->toArray();
        $dataCounts = $results->pluck('total')->toArray();
        $percentages = $results->map(fn ($item) => round(($item->total / $totalAgents) * 100, 1))->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Agentes',
                    'data' => $dataCounts,
                    'percentages' => array_values($percentages),
                    'backgroundColor' => $this->getStateColors(count($labels)),
                    'hoverOffset' => 20,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                    'hoverBorderWidth' => 4,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1200,
                    easing: 'easeOutQuart'
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        align: 'center',
                        onClick: function(e, legendItem, legend) {
                            const idx = legendItem.index;
                            const chart = legend.chart;
                            const meta = chart.getDatasetMeta(0);
                            meta.data[idx].hidden = !meta.data[idx].hidden;
                            chart.update();
                        },
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14,
                            boxWidth: 12,
                            boxHeight: 12,
                            font: {
                                size: 13,
                                weight: '600',
                                family: 'ui-sans-serif, system-ui, sans-serif'
                            },
                            color: function(context) {
                                const isDark = document.documentElement.classList.contains('dark');
                                return isDark ? '#f1f5f9' : '#0f172a';
                            },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                return data.labels.map((label, i) => {
                                    const value = data.datasets[0].data[i];
                                    const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    const agentes = value === 1 ? ' agente' : ' agentes';
                                    return {
                                        text: label + ': ' + value + agentes + ' (' + pct + '%)',
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        strokeStyle: data.datasets[0].backgroundColor[i],
                                        hidden: chart.getDatasetMeta(0).data[i].hidden,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: function(context) {
                            const isDark = document.documentElement.classList.contains('dark');
                            return isDark ? 'rgba(30, 41, 59, 0.96)' : 'rgba(255, 255, 255, 0.98)';
                        },
                        titleColor: function(context) {
                            const isDark = document.documentElement.classList.contains('dark');
                            return isDark ? '#f1f5f9' : '#0f172a';
                        },
                        bodyColor: function(context) {
                            const isDark = document.documentElement.classList.contains('dark');
                            return isDark ? '#e2e8f0' : '#334155';
                        },
                        borderColor: function(context) {
                            const isDark = document.documentElement.classList.contains('dark');
                            return isDark ? 'rgba(248, 250, 252, 0.2)' : 'rgba(0, 0, 0, 0.08)';
                        },
                        borderWidth: 1,
                        padding: 14,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                const pct = context.dataset.percentages[context.dataIndex];
                                return ' ' + value + ' agente(s) (' + pct + '%)';
                            },
                            afterLabel: function(context) {
                                return ' Clic en la leyenda para mostrar/ocultar';
                            }
                        }
                    },
                    datalabels: {
                        display: function(context) {
                            const pct = context.dataset.percentages[context.dataIndex];
                            return pct >= 4;
                        },
                        color: '#ffffff',
                        anchor: 'center',
                        align: 'center',
                        font: {
                            size: 13,
                            weight: 'bold',
                            family: 'sans-serif'
                        },
                        formatter: function(value, context) {
                            const pct = context.dataset.percentages[context.dataIndex];
                            return pct + '%';
                        },
                        textShadowColor: 'rgba(0, 0, 0, 0.6)',
                        textShadowBlur: 3
                    }
                },
                layout: {
                    padding: { top: 16, bottom: 16, left: 16, right: 16 }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
