<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Filament\Business\Resources\Agencies\Concerns\HasAgencyResourceChartTimeStateFilters;
use App\Models\Agency;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class AgencyGeoChart extends ChartWidget
{
    use HasAgencyResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.agency-geo-chart';

    public function mount(): void
    {
        parent::mount();
        $this->bootAgencyChartFilters();

        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected ?string $heading = 'Distribución de Agencias por Estado';

    protected ?string $description = 'Altas de agencias activas por estado (año y mes de registro).';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 1;

    /** Misma altura de área de gráfico que {@see NewRegisterAgencyForMountChart}. */
    protected ?string $maxHeight = '440px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        $distribution = Agency::query()
            ->join('states', 'agencies.state_id', '=', 'states.id')
            ->selectRaw('states.definition as state_name, COUNT(*) as total')
            ->where('agencies.status', 'ACTIVO')
            ->whereYear('agencies.created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('agencies.created_at', $month))
            ->groupBy('states.definition')
            ->orderByDesc('total')
            ->pluck('total', 'state_name')
            ->toArray();

        $vibrantPalette = [
            '#FF2D55', // Rosa Apple
            '#5856D6', // Púrpura Apple
            '#34C759', // Verde Apple
            '#FF9500', // Naranja Apple
            '#007AFF', // Azul Apple
            '#AF52DE', // Índigo
            '#FFCC00', // Amarillo
            '#5AC8FA', // Cian
            '#FF3B30', // Rojo
            '#2dd4bf', // Teal
            '#f472b6', // Rosa fuerte
            '#a78bfa', // Violeta claro
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
                    'label' => 'Agencias Activas',
                    'data' => $data,
                    'percentages' => array_values($percentages),
                    'backgroundColor' => $backgroundColors,
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
                            size: 12,
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
                                    text: String(label) + ': ' + value + ' agencias (' + pct + '%)',
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
                            return ` ${context.label}: ${value} agencias (${pct}%)`;
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
            // Configuraciones de interacción para el resaltado
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
            // Efecto de énfasis al posicionar el cursor
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            }
        }
        JS);
    }
}
