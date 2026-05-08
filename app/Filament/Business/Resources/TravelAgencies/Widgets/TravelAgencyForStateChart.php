<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Widgets;

use App\Filament\Business\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\State;
use App\Models\TravelAgency;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

class TravelAgencyForStateChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Agencias de viaje por estado';

    protected ?string $description = 'Totales por estado (respeta búsqueda y filtros del listado).';

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    public function mount(): void
    {
        parent::mount();

        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected function getTablePage(): string
    {
        return ListTravelAgencies::class;
    }

    protected function getData(): array
    {
        $table = (new TravelAgency)->getTable();
        $base = $this->getPageTableQuery();

        $distinctStateIds = (clone $base)
            ->reorder()
            ->whereNotNull("{$table}.state_id")
            ->distinct()
            ->pluck("{$table}.state_id");

        $stateRows = State::query()
            ->whereIn('id', $distinctStateIds)
            ->orderBy('definition')
            ->get();

        $hasWithoutState = (clone $base)->reorder()->whereNull("{$table}.state_id")->exists();

        $labels = $stateRows
            ->map(fn (State $state): string => Str::limit($state->definition, 22))
            ->values()
            ->all();

        if ($hasWithoutState) {
            $labels[] = 'Sin estado';
        }

        if ($labels === []) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [
                    [
                        'label' => 'Agencias de viaje',
                        'data' => [0],
                        'backgroundColor' => 'rgba(142, 142, 147, 0.25)',
                        'borderWidth' => 0,
                        'borderColor' => 'transparent',
                    ],
                ],
            ];
        }

        $filteredIdsQuery = (clone $base)
            ->reorder()
            ->select("{$table}.id")
            ->distinct();

        $aggregates = TravelAgency::query()
            ->whereIn('id', $filteredIdsQuery)
            ->selectRaw('state_id, COUNT(*) as cnt')
            ->groupBy('state_id')
            ->get();

        /** @var array<string, int> $countMap */
        $countMap = [];
        foreach ($aggregates as $row) {
            $key = $row->state_id === null ? 'null_state' : (string) $row->state_id;
            $countMap[$key] = (int) $row->cnt;
        }

        $data = [];
        foreach ($stateRows as $state) {
            $data[] = $countMap[(string) $state->id] ?? 0;
        }
        if ($hasWithoutState) {
            $data[] = $countMap['null_state'] ?? 0;
        }

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

        $total = (int) array_sum($data);
        $percentages = $total > 0
            ? array_map(
                static fn (mixed $n): float => round(((float) $n / $total) * 100, 1),
                $data
            )
            : [];

        $backgroundColors = array_map(static function (int $index) use ($vibrantPalette): string {
            return $vibrantPalette[$index % count($vibrantPalette)];
        }, array_keys($data));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Agencias de viaje',
                    'data' => $data,
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
        return 'pie';
    }
}
