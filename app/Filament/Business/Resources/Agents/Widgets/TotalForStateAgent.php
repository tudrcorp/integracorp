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
 * Gráfico de barras: agentes agrupados por estado para un único mes/año.
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

        return 'agent-state-bar-'.hash('xxh128', json_encode([$y, $m]));
    }

    /**
     * Agrega agentes por estado usando solo `created_at` en el mes/año del filtro.
     */
    protected function getData(): array
    {
        [$year, $month] = $this->resolveSelectedYearMonth();

        $agentsByState = DB::table('agents')
            ->leftJoin('states', 'agents.state_id', '=', 'states.id')
            ->whereYear('agents.created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('agents.created_at', $month))
            ->select(
                DB::raw("COALESCE(states.definition, 'Sin estado') as state_name"),
                DB::raw('agents.id as agent_id'),
                DB::raw('agents.code_agent as agent_code'),
                DB::raw('agents.name as agent_name')
            )
            ->orderBy('state_name')
            ->orderBy('agents.name')
            ->get()
            ->groupBy('state_name')
            ->sortByDesc(static fn ($agents): int => $agents->count());

        if ($agentsByState->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Agentes',
                        'data' => [],
                        'percentages' => [],
                        'agentDetails' => [],
                        'backgroundColor' => [],
                        'borderWidth' => 0,
                        'borderColor' => 'transparent',
                        'hoverBorderWidth' => 0,
                        'hoverBorderColor' => 'transparent',
                        'borderRadius' => 4,
                        'borderSkipped' => false,
                    ],
                ],
            ];
        }

        $labels = $agentsByState->keys()->values()->all();
        $dataCounts = $agentsByState->map(static fn ($agents): int => $agents->count())->values()->all();
        $agentDetails = $agentsByState
            ->map(static function ($agents): array {
                return $agents
                    ->map(static function ($agent): array {
                        $name = trim((string) ($agent->agent_name ?? ''));
                        $code = trim((string) ($agent->agent_code ?? ''));

                        if ($code === '') {
                            $agentId = (int) ($agent->agent_id ?? 0);
                            $code = $agentId > 0 ? 'AGT-000'.$agentId : 'SIN-CODIGO';
                        }

                        return [
                            'code' => $code,
                            'name' => $name !== '' ? $name : 'Sin nombre',
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
        $totalAgents = (int) array_sum($dataCounts);
        $percentages = $totalAgents > 0
            ? array_map(
                static fn (int $count): float => round(($count / $totalAgents) * 100, 1),
                $dataCounts
            )
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
                    'agentDetails' => $agentDetails,
                    'backgroundColor' => $fills,
                    'borderWidth' => 0,
                    'borderColor' => 'transparent',
                    'hoverBorderWidth' => 0,
                    'hoverBorderColor' => 'transparent',
                    'borderRadius' => 4,
                    'borderSkipped' => false,
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
            layout: {
                padding: { top: 16, right: 8, bottom: 6, left: 8 }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
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
                            const agents = Array.isArray(context.dataset.agentDetails)
                                ? context.dataset.agentDetails[context.dataIndex]
                                : [];

                            if (!Array.isArray(agents) || agents.length === 0) {
                                return ' Sin agentes disponibles';
                            }

                            return agents.map((agent) => {
                                const code = agent && agent.code ? String(agent.code) : 'SIN-CODIGO';
                                const name = agent && agent.name ? String(agent.name) : 'Sin nombre';
                                return ` ${code} - ${name}`;
                            });
                        },
                        footer: (items) => {
                            const first = Array.isArray(items) ? items[0] : null;
                            if (!first) {
                                return '';
                            }
                            const value = first.raw || 0;
                            const pct = first.dataset.percentages[first.dataIndex];
                            const agentes = value === 1 ? 'agente' : 'agentes';
                            return `Total: ${value} ${agentes} (${pct}%)`;
                        },
                    }
                },
                datalabels: {
                    display: function(context) {
                        const pct = context.dataset.percentages[context.dataIndex];
                        return pct >= 4;
                    },
                    color: '#1e293b',
                    anchor: 'end',
                    align: 'end',
                    offset: 4,
                    font: {
                        size: 12,
                        weight: '700',
                        family: 'ui-sans-serif, -apple-system, system-ui, sans-serif'
                    },
                    formatter: function(value, context) {
                        const pct = context.dataset.percentages[context.dataIndex];
                        return pct + '%';
                    }
                }
            },
            hover: {
                mode: 'nearest',
                intersect: true
            },
            animation: {
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
        return 'bar';
    }
}
