<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\City;
use App\Models\State;
use Filament\Notifications\Notification;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliationCorporatePorEstadoChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliationCorporates::class;
    }

    protected ?string $heading = 'RESUMEN DE AFILIACIONES CORPORATIVAS POR UBICACIÓN';

    protected ?string $description = 'Total de afiliaciones activas por estado. Haz clic en un segmento para ver el detalle de ciudades.';

    protected ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 1;

    /**
     * Estado para controlar el Drill-down
     */
    public ?int $selectedStateId = null;

    public function mount(): void
    {
        parent::mount();

        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    /**
     * Maneja el clic desde el frontend
     */
    public function handleChartClick(array $payload): void
    {
        if ($this->selectedStateId === null) {
            $state = State::where('definition', $payload['label'])->first();

            if ($state) {
                $this->selectedStateId = $state->id;

                Notification::make()
                    ->title("Detalle: {$state->definition}")
                    ->body('Mostrando afiliaciones activas por ciudad.')
                    ->info()
                    ->send();
            }

            return;
        }

        $this->selectedStateId = null;

        Notification::make()
            ->title('Vista Nacional')
            ->body('Regresando al resumen por estados.')
            ->success()
            ->send();
    }

    protected function getData(): array
    {
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

        if ($this->selectedStateId) {
            $stateName = State::find($this->selectedStateId)?->definition ?? 'Estado';

            $stats = $this->getPageTableQuery()
                ->reorder()
                ->where('status', 'ACTIVA')
                ->where('state_id', $this->selectedStateId)
                ->select('city_id', DB::raw('count(*) as total'))
                ->groupBy('city_id')
                ->having('total', '>', 0)
                ->orderByDesc('total')
                ->get();

            $cityNames = City::query()
                ->whereIn('id', $stats->pluck('city_id')->filter()->all())
                ->pluck('definition', 'id');

            $labels = [];
            $values = [];

            foreach ($stats as $stat) {
                $cityName = $cityNames->get($stat->city_id) ?? "Ciudad #{$stat->city_id}";
                $labels[] = Str::limit((string) $cityName, 28);
                $values[] = (int) $stat->total;
            }

            $datasetLabel = "Afiliaciones en {$stateName}";
        } else {
            $stats = $this->getPageTableQuery()
                ->reorder()
                ->where('status', 'ACTIVA')
                ->whereNotNull('state_id')
                ->select('state_id', DB::raw('count(*) as total'))
                ->groupBy('state_id')
                ->having('total', '>', 0)
                ->orderByDesc('total')
                ->get();

            $stateNames = State::query()
                ->whereIn('id', $stats->pluck('state_id')->all())
                ->pluck('definition', 'id');

            $labels = [];
            $values = [];

            foreach ($stats as $stat) {
                $stateName = $stateNames->get($stat->state_id) ?? "Estado #{$stat->state_id}";
                $labels[] = (string) $stateName;
                $values[] = (int) $stat->total;
            }

            $datasetLabel = 'Afiliaciones por Estado (total)';
        }

        if ($labels === []) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [
                    [
                        'label' => $datasetLabel,
                        'data' => [0],
                        'percentages' => [0],
                        'backgroundColor' => ['rgba(142, 142, 147, 0.25)'],
                        'borderWidth' => 0,
                        'borderColor' => 'transparent',
                    ],
                ],
            ];
        }

        $total = (int) array_sum($values);
        $percentages = $total > 0
            ? array_map(
                static fn (int $n): float => round(($n / $total) * 100, 1),
                $values
            )
            : array_fill(0, count($values), 0.0);

        $backgroundColors = array_map(
            static fn (int $index): string => $vibrantPalette[$index % count($vibrantPalette)],
            array_keys($values)
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $values,
                    'percentages' => array_values($percentages),
                    'backgroundColor' => $backgroundColors,
                    'borderWidth' => 0,
                    'borderColor' => 'transparent',
                    'radius' => '95%',
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
            layout: {
                padding: { top: 8, right: 4, bottom: 0, left: 4 }
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
                                    text: String(label) + ': ' + value + ' (' + pct + '%)',
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
                            return ` ${context.label}: ${value} afiliaciones (${pct}%)`;
                        },
                        footer: () => 'Haz clic para ver ciudades / regresar'
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
            onClick: (event, elements, chart) => {
                if (elements && elements.length > 0) {
                    const activeElement = elements[0];
                    const dataIndex = activeElement.index;
                    const label = chart.data.labels[dataIndex];

                    $wire.handleChartClick({
                        label: label,
                        indice: dataIndex
                    });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
