<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Concerns\HasProspectResourceChartTimeStateFilters;
use App\Models\ProspectAgent;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class TopRegisterProspectForState extends ChartWidget
{
    use HasProspectResourceChartTimeStateFilters;

    public function mount(): void
    {
        parent::mount();
        $this->bootProspectChartFilters();

        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Prospectos registrados por estado';

    protected ?string $description = 'Distribución de prospectos por estado/región.';

    protected ?string $maxHeight = '400px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        $distribution = ProspectAgent::query()
            ->join('states', 'prospect_agents.state_id', '=', 'states.id')
            ->selectRaw('states.definition as state_name, COUNT(*) as total')
            ->whereYear('prospect_agents.created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('prospect_agents.created_at', $month))
            ->groupBy('states.definition')
            ->orderByDesc('total')
            ->pluck('total', 'state_name')
            ->toArray();

        $vibrantPalette = [
            '#FF2D55',
            '#5856D6',
            '#34C759',
            '#FF9500',
            '#007AFF',
            '#AF52DE',
            '#FFCC00',
            '#5AC8FA',
            '#FF3B30',
            '#2dd4bf',
            '#f472b6',
            '#a78bfa',
        ];

        $labels = array_keys($distribution);
        $data = array_values($distribution);

        $total = (int) array_sum($data);
        $percentages = $total > 0
            ? array_map(
                static fn (mixed $n): float => round(((float) $n / $total) * 100, 1),
                $data
            )
            : [];

        $backgroundColors = array_map(function ($index) use ($vibrantPalette) {
            return $vibrantPalette[$index % count($vibrantPalette)];
        }, array_keys($data));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Prospectos',
                    'data' => $data,
                    'percentages' => array_values($percentages),
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => 'transparent',
                    'hoverOffset' => 35,
                    'hoverBorderWidth' => 3,
                    'hoverBorderColor' => '#ffffff',
                    'borderRadius' => 4,
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
            cutout: '65%',
            layout: {
                padding: 40
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        font: {
                            size: 13,
                            weight: '600'
                        },
                        color: 'gray',
                        generateLabels: function(chart) {
                            const data = chart.data;
                            const ds = data.datasets[0];
                            const meta = chart.getDatasetMeta(0);
                            return data.labels.map((label, i) => {
                                const value = ds.data[i];
                                const pct = Array.isArray(ds.percentages) && ds.percentages[i] !== undefined
                                    ? ds.percentages[i]
                                    : 0;
                                const fill = Array.isArray(ds.backgroundColor) ? ds.backgroundColor[i] : ds.backgroundColor;
                                const prospectos = value === 1 ? ' prospecto' : ' prospectos';
                                return {
                                    text: String(label) + ': ' + value + prospectos + ' (' + pct + '%)',
                                    fillStyle: fill,
                                    strokeStyle: fill,
                                    lineWidth: 0,
                                    hidden: meta.data[i] ? meta.data[i].hidden : false,
                                    index: i,
                                    datasetIndex: 0
                                };
                            });
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#1e293b',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: (context) => {
                            const value = context.raw || 0;
                            const pct = context.dataset.percentages[context.dataIndex];
                            return ` ${context.label}: ${value} prospecto(s) (${pct}%)`;
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
                        size: 12,
                        weight: '700',
                        family: 'ui-sans-serif, -apple-system, system-ui, sans-serif'
                    },
                    formatter: function(value, context) {
                        const pct = context.dataset.percentages[context.dataIndex];
                        return pct + '%';
                    },
                    textShadowColor: 'rgba(0, 0, 0, 0.55)',
                    textShadowBlur: 3
                }
            },
            hover: {
                mode: 'nearest',
                intersect: true
            },
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1500,
                easing: 'easeOutQuart'
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            }
        }
        JS);
    }
}
