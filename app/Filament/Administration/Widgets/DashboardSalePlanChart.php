<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets;

use App\Models\Sale;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DashboardSalePlanChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.sale-plan-chart';

    protected string $color = 'gray';

    public int $chartKey = 0;

    protected ?string $heading = 'DISTRIBUCIÓN DE VENTAS POR PLAN';

    protected ?string $description = 'Análisis porcentual y cuantitativo de planes vendidos en el mes actual.';

    protected ?string $maxHeight = '350px';

    /**
     * @var int | string | array<string, int | string | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    public function mount(): void
    {
        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);

        parent::mount();
    }

    protected function getData(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $salesData = Sale::query()
            ->select('plan_id', DB::raw('count(*) as total'))
            ->where('is_payment_link', false)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('plan_id')
            ->get();

        $totalSales = (int) $salesData->sum('total');

        if ($totalSales === 0) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [['data' => [0], 'backgroundColor' => ['#e5e7eb']]],
            ];
        }

        $counts = [
            'inicial' => $salesData->firstWhere('plan_id', 1)->total ?? 0,
            'ideal' => $salesData->firstWhere('plan_id', 2)->total ?? 0,
            'especial' => $salesData->firstWhere('plan_id', 3)->total ?? 0,
            'corp' => $salesData->whereNull('plan_id')->first()->total ?? 0,
        ];

        $labels = ['Plan Inicial', 'Plan Ideal', 'Plan Especial', 'Corporativo'];
        $data = [
            (int) $counts['inicial'],
            (int) $counts['ideal'],
            (int) $counts['especial'],
            (int) $counts['corp'],
        ];

        $percentages = array_map(
            static fn (int $n): float => round(($n / $totalSales) * 100, 1),
            $data
        );

        $vibrantPalette = [
            '#FF2D55',
            '#5856D6',
            '#34C759',
            '#FF9500',
        ];

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'label' => 'Ventas por plan',
                    'percentages' => array_values($percentages),
                    'backgroundColor' => $vibrantPalette,
                    'borderWidth' => 0,
                    'borderColor' => 'transparent',
                    'hoverOffset' => 35,
                    'hoverBorderWidth' => 0,
                    'hoverBorderColor' => 'transparent',
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
            borderWidth: 0,
            elements: {
                arc: {
                    borderWidth: 0,
                    borderColor: 'transparent'
                }
            },
            cutout: '52%',
            layout: {
                padding: { top: 16, right: 8, bottom: 6, left: 8 }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    align: 'center',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 18,
                        boxWidth: 10,
                        boxHeight: 10,
                        font: {
                            size: 13,
                            weight: '600',
                            family: 'ui-sans-serif, -apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        },
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
                                return {
                                    text: String(label) + ': ' + value + ' ventas (' + pct + '%)',
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
                            const pct = context.dataset.percentages?.[context.dataIndex] ?? 0;
                            return ` ${context.label}: ${value} ventas (${pct}%)`;
                        }
                    }
                },
                datalabels: {
                    display: function(context) {
                        const pct = context.dataset.percentages?.[context.dataIndex] ?? 0;
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
                        const pct = context.dataset.percentages?.[context.dataIndex] ?? 0;
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

    protected function getType(): string
    {
        return 'doughnut';
    }
}
