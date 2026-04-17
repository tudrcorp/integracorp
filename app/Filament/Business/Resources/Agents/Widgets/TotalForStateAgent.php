<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Widgets\Concerns\HasYearMonthChartFilters;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gráfico de dona: agentes agrupados por estado para un único mes/año.
 *
 * A diferencia de un widget enlazado a la tabla ({@see \App\Filament\Widgets\Concerns\InteractsWithPageTable}),
 * esta vista **no** aplica búsqueda, filtros ni orden de la lista de agentes: solo el período elegido en el
 * selector del propio widget (registros filtrados por la columna `created_at` del modelo {@see \App\Models\Agent}).
 *
 * Período: dos desplegables (año y mes). Si el año elegido es el actual, el mes solo lista meses ya transcurridos
 * (enero hasta el mes en curso).
 */
class TotalForStateAgent extends ChartWidget
{
    use HasYearMonthChartFilters;

    protected string $view = 'filament.widgets.total-for-state-agent-chart';

    public function mount(): void
    {
        $this->applyDefaultYearMonthForMount();

        parent::mount();

        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected ?string $heading = 'Agentes por estado';

    protected ?string $description = 'Distribución por estado de los agentes creados en el período seleccionado. No usa los filtros de la tabla.';

    protected int|string|array $columnSpan = 1;

    /**
     * Misma paleta que {@see \App\Filament\Business\Resources\Agencies\Widgets\AgencyGeoChart} (distribución por estado).
     *
     * @var list<string>
     */
    private const VIBRANT_PALETTE = [
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
        [$year, $month] = $this->resolveSelectedYearMonth();
        $label = $month === null
            ? "Todo el año {$year}"
            : Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y');

        return "No hay agentes registrados en {$label}. Prueba otro período en los filtros del gráfico.";
    }

    /**
     * Clave estable para el canvas: año y mes del widget (no depende de la tabla).
     */
    public function getStateDistributionChartWireKey(): string
    {
        [$y, $m] = $this->resolveSelectedYearMonth();

        return 'agent-state-pie-'.hash('xxh128', json_encode([$y, $m]));
    }

    /**
     * Agrega agentes por estado usando solo `created_at` en el mes/año del filtro.
     */
    protected function getData(): array
    {
        [$year, $month] = $this->resolveSelectedYearMonth();

        $results = DB::table('agents')
            ->leftJoin('states', 'agents.state_id', '=', 'states.id')
            ->whereYear('agents.created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('agents.created_at', $month))
            ->select(
                DB::raw("COALESCE(states.definition, 'Sin estado') as state_name"),
                DB::raw('states.id as state_id'),
                DB::raw('count(agents.id) as total')
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

        $totalAgents = (int) $results->sum('total');
        $labels = $results->pluck('state_name')->all();
        $dataCounts = $results->pluck('total')->map(fn ($n) => (int) $n)->all();
        $percentages = $totalAgents > 0
            ? $results->map(fn ($item) => round(((int) $item->total / $totalAgents) * 100, 1))->all()
            : [];

        $fills = [];
        foreach (array_keys($labels) as $index) {
            $fills[] = self::VIBRANT_PALETTE[$index % count(self::VIBRANT_PALETTE)];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Agentes',
                    'data' => $dataCounts,
                    'percentages' => array_values($percentages),
                    'backgroundColor' => $fills,
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
                                const agentes = value === 1 ? ' agente' : ' agentes';
                                return {
                                    text: String(label) + ': ' + value + agentes + ' (' + pct + '%)',
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
                            return ` ${context.label}: ${value} agente(s) (${pct}%)`;
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
        return 'doughnut';
    }
}
