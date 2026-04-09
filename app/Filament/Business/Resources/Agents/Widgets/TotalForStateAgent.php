<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Business\Resources\Agents\Pages\ListAgents;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TotalForStateAgent extends ChartWidget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.widgets.total-for-state-agent-chart';

    protected function getTablePage(): string
    {
        return ListAgents::class;
    }

    public function mount(): void
    {
        parent::mount();

        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected ?string $heading = 'Agentes por estado';

    protected ?string $description = 'Distribución de agentes registrados por estado. El gráfico respeta los filtros de la tabla.';

    protected int|string|array $columnSpan = 'full';

    /**
     * Paleta tipo iOS (alineada con NewRegisterAgentForMountChart).
     *
     * @var list<string>
     */
    private const SLICE_FILL = [
        'rgba(59, 130, 246, 0.94)',
        'rgba(16, 185, 129, 0.94)',
        'rgba(249, 115, 22, 0.94)',
        'rgba(244, 63, 94, 0.94)',
        'rgba(168, 85, 247, 0.94)',
        'rgba(14, 165, 233, 0.94)',
        'rgba(234, 179, 8, 0.94)',
        'rgba(139, 92, 246, 0.94)',
        'rgba(34, 197, 94, 0.94)',
        'rgba(245, 158, 11, 0.94)',
        'rgba(6, 182, 212, 0.94)',
        'rgba(217, 70, 239, 0.94)',
    ];

    /**
     * @var list<string>
     */
    private const SLICE_BORDER = [
        'rgba(29, 78, 216, 1)',
        'rgba(5, 150, 105, 1)',
        'rgba(194, 65, 12, 1)',
        'rgba(225, 29, 72, 1)',
        'rgba(126, 34, 206, 1)',
        'rgba(3, 105, 161, 1)',
        'rgba(161, 98, 7, 1)',
        'rgba(109, 40, 217, 1)',
        'rgba(21, 128, 61, 1)',
        'rgba(180, 83, 9, 1)',
        'rgba(8, 145, 178, 1)',
        'rgba(162, 28, 175, 1)',
    ];

    /**
     * Suma de agentes en el dataset actual (respeta caché del widget).
     */
    public function getAgentsTotalInCurrentView(): int
    {
        $data = $this->getCachedData();

        return (int) collect($data['datasets'][0]['data'] ?? [])->sum();
    }

    public function getEmptyStateMessage(): string
    {
        return 'No hay agentes que coincidan con los filtros de la tabla. Ajusta la búsqueda o los filtros para ver la distribución por estado.';
    }

    /**
     * Fuerza recreación del canvas Alpine cuando cambian filtros / orden de la tabla.
     */
    public function getStateDistributionChartWireKey(): string
    {
        $payload = [
            'search' => $this->tableSearch ?? '',
            'filters' => $this->tableFilters,
            'sort' => $this->tableSort,
            'grouping' => $this->tableGrouping,
            'tab' => $this->activeTab,
            'columnSearches' => $this->tableColumnSearches,
            'perPage' => $this->tableRecordsPerPage,
            'parentRecord' => $this->parentRecord?->getKey(),
        ];

        return 'agent-state-pie-'.hash('xxh128', (string) json_encode($payload));
    }

    protected function getData(): array
    {
        $baseQuery = $this->getPageTableQuery();
        $table = $baseQuery->getModel()->getTable();

        $subQuery = clone $baseQuery;
        $subQuery->reorder();
        $subQuery->select("{$table}.id", "{$table}.state_id");

        $results = DB::table(DB::raw('('.$subQuery->toSql().') as '.$table))
            ->mergeBindings($subQuery->getQuery())
            ->leftJoin('states', "{$table}.state_id", '=', 'states.id')
            ->select(
                DB::raw('COALESCE(states.definition, "Sin estado") as state_name'),
                DB::raw('states.id as state_id'),
                DB::raw("count({$table}.id) as total")
            )
            ->groupBy('states.id', 'states.definition')
            ->orderByDesc('total')
            ->get();

        if ($results->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Agentes',
                        'data' => [],
                        'percentages' => [],
                        'backgroundColor' => [],
                        'borderColor' => [],
                    ],
                ],
            ];
        }

        $totalAgents = (int) $results->sum('total');
        $labels = $results->pluck('state_name')->all();
        $dataCounts = $results->pluck('total')->map(fn ($n) => (int) $n)->all();
        $percentages = $totalAgents > 0
            ? $results->map(fn ($item) => round(((int) $item->total / $totalAgents) * 100, 1))->all()
            : [];

        $fills = [];
        $borders = [];
        foreach (array_keys($labels) as $index) {
            $fills[] = self::SLICE_FILL[$index % count(self::SLICE_FILL)];
            $borders[] = self::SLICE_BORDER[$index % count(self::SLICE_BORDER)];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Agentes',
                    'data' => $dataCounts,
                    'percentages' => array_values($percentages),
                    'backgroundColor' => $fills,
                    'hoverOffset' => 14,
                    'borderColor' => $borders,
                    'borderWidth' => 2.5,
                    'hoverBorderWidth' => 3.5,
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.92)',
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
                    duration: 900,
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
                            padding: 12,
                            boxWidth: 11,
                            boxHeight: 11,
                            font: {
                                size: 12,
                                weight: '600',
                                family: 'ui-sans-serif, -apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                            },
                            color: function(context) {
                                const isDark = document.documentElement.classList.contains('dark');
                                return isDark ? 'rgba(241, 245, 249, 0.95)' : 'rgba(15, 23, 42, 0.92)';
                            },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                return data.labels.map((label, i) => {
                                    const value = data.datasets[0].data[i];
                                    const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                    const agentes = value === 1 ? ' agente' : ' agentes';
                                    return {
                                        text: label + ': ' + value + agentes + ' (' + pct + '%)',
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        strokeStyle: data.datasets[0].borderColor[i],
                                        hidden: chart.getDatasetMeta(0).data[i].hidden,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(22, 22, 24, 0.56)',
                        titleColor: 'rgba(255, 255, 255, 0.92)',
                        bodyColor: 'rgba(255, 255, 255, 0.86)',
                        borderColor: 'rgba(255, 255, 255, 0.14)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 12,
                        displayColors: true,
                        boxPadding: 6,
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                const pct = context.dataset.percentages[context.dataIndex];
                                return ' ' + value + ' agente(s) (' + pct + '%)';
                            },
                            afterLabel: function() {
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
                layout: {
                    padding: { top: 12, bottom: 12, left: 8, right: 8 }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
