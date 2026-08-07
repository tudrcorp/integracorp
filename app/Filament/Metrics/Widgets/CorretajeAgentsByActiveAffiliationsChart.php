<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\HasMetricsChartPerformance;
use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Services\IntegracorpApi\CorretajeAgentsMetricsClient;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CorretajeAgentsByActiveAffiliationsChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '28rem';

    protected string $view = 'filament.metrics.widgets.corretaje-agents-by-active-affiliations-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Agentes por afiliaciones activas';

    protected ?string $description = 'Individuales y corporativas ACTIVA con agent_id · top 20. Dos barras si el agente tiene ambos tipos.';

    /**
     * @var array{
     *     items: list<array{agent_id: int, agent_name: string, code_agent: string|null, total_individual: int, total_corporate: int, total: int}>,
     *     total_affiliations: int,
     *     total_individual_affiliations: int,
     *     total_corporate_affiliations: int,
     *     total_agents: int,
     *     limit: int
     * }|null
     */
    private ?array $resolvedPayload = null;

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin afiliaciones activas por agente';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay afiliaciones ACTIVA (individuales o corporativas) asociadas a un agent_id para graficar.';
    }

    public function getChartTotalAffiliations(): int
    {
        return (int) ($this->resolvePayload()['total_affiliations'] ?? 0);
    }

    public function getChartIndividualAffiliations(): int
    {
        return (int) ($this->resolvePayload()['total_individual_affiliations'] ?? 0);
    }

    public function getChartCorporateAffiliations(): int
    {
        return (int) ($this->resolvePayload()['total_corporate_affiliations'] ?? 0);
    }

    public function getChartAgentsCount(): int
    {
        return (int) ($this->resolvePayload()['total_agents'] ?? 0);
    }

    public function getChartTopAgentLabel(): string
    {
        $top = $this->resolveItems()[0] ?? null;

        if ($top === null) {
            return '—';
        }

        return $this->formatAgentLabel($top['agent_name'], $top['code_agent'], short: true);
    }

    public function getChartTopAgentTotal(): int
    {
        return (int) (($this->resolveItems()[0]['total'] ?? 0));
    }

    public function getMaxHeight(): ?string
    {
        return '420px';
    }

    public function getIosBarChartWireKey(): string
    {
        $fingerprint = hash(
            'xxh128',
            json_encode([
                $this->getChartTotalAffiliations(),
                $this->getChartIndividualAffiliations(),
                $this->getChartCorporateAffiliations(),
                $this->getChartAgentsCount(),
                $this->getChartTopAgentTotal(),
                count($this->resolveItems()),
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-agents-by-active-affiliations-'.$fingerprint;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $items = $this->resolveItems();
        $borderRadius = [
            'topLeft' => 10,
            'topRight' => 10,
            'bottomLeft' => 0,
            'bottomRight' => 0,
        ];

        if ($items === []) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Individuales',
                        'data' => [],
                        'backgroundColor' => 'rgba(20, 184, 166, 0.88)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                        'skipNull' => true,
                    ],
                    [
                        'label' => 'Corporativas',
                        'data' => [],
                        'backgroundColor' => 'rgba(124, 58, 237, 0.88)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                        'skipNull' => true,
                    ],
                ],
            ];
        }

        $labels = [];
        $fullNames = [];
        $individualValues = [];
        $corporateValues = [];

        foreach ($items as $item) {
            $labels[] = $this->formatAgentLabel($item['agent_name'], $item['code_agent'], short: true);
            $fullNames[] = $this->formatAgentLabel($item['agent_name'], $item['code_agent'], short: false);
            $individualValues[] = $item['total_individual'] > 0 ? $item['total_individual'] : null;
            $corporateValues[] = $item['total_corporate'] > 0 ? $item['total_corporate'] : null;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Individuales',
                    'data' => $individualValues,
                    'fullNames' => $fullNames,
                    'backgroundColor' => 'rgba(20, 184, 166, 0.88)',
                    'hoverBackgroundColor' => 'rgba(20, 184, 166, 1)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 44,
                    'skipNull' => true,
                ],
                [
                    'label' => 'Corporativas',
                    'data' => $corporateValues,
                    'fullNames' => $fullNames,
                    'backgroundColor' => 'rgba(124, 58, 237, 0.88)',
                    'hoverBackgroundColor' => 'rgba(124, 58, 237, 1)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 44,
                    'skipNull' => true,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
{
    indexAxis: 'x',
    responsive: true,
    maintainAspectRatio: false,
    devicePixelRatio: Math.min(window.devicePixelRatio || 1, 1.5),
    layout: {
        padding: { top: 28, right: 8, bottom: 4, left: 4 }
    },
    interaction: {
        mode: 'index',
        intersect: false
    },
    datasets: {
        bar: {
            categoryPercentage: 0.78,
            barPercentage: 0.9
        }
    },
    elements: {
        bar: {
            borderWidth: 0,
            borderRadius: {
                topLeft: 10,
                topRight: 10,
                bottomLeft: 0,
                bottomRight: 0
            },
            borderSkipped: false
        }
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
                boxWidth: 10,
                boxHeight: 10,
                borderRadius: 3,
                useBorderRadius: true,
                padding: 12,
                color: '#64748b',
                font: { size: 11, weight: '600' }
            }
        },
        tooltip: {
            enabled: true,
            backgroundColor: 'rgba(15, 23, 42, 0.82)',
            titleColor: 'rgba(255, 255, 255, 0.96)',
            bodyColor: 'rgba(226, 232, 240, 0.92)',
            borderColor: 'rgba(45, 212, 191, 0.35)',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            boxPadding: 6,
            cornerRadius: 10,
            titleFont: { weight: '600', size: 12 },
            bodyFont: { size: 12 },
            filter: (item) => item.parsed?.y !== null && item.parsed?.y !== undefined,
            callbacks: {
                title: (items) => {
                    const first = items?.[0];
                    if (!first) return '';
                    const full = first.dataset?.fullNames?.[first.dataIndex];
                    return full || first.label || '';
                },
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return null;
                    const kind = ctx.dataset?.label || 'Afiliaciones';
                    return ' ' + kind + ': ' + Number(v).toLocaleString('es-VE');
                }
            }
        }
    },
    scales: {
        x: {
            grid: { display: false, drawBorder: false },
            border: { display: false },
            ticks: {
                color: '#475569',
                font: { size: 10, weight: '600' },
                maxRotation: 45,
                minRotation: 35,
                autoSkip: false,
                padding: 4
            }
        },
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(100, 116, 139, 0.14)',
                drawBorder: false
            },
            border: { display: false },
            ticks: {
                color: '#64748b',
                precision: 0,
                font: { size: 11, weight: '500' },
                maxTicksLimit: 6
            }
        }
    },
    animation: {
        duration: 260,
        easing: 'easeOutCubic'
    },
    transitions: {
        active: { animation: { duration: 180 } },
        resize: { animation: { duration: 0 } }
    }
}
JS);
    }

    /**
     * @return list<array{agent_id: int, agent_name: string, code_agent: string|null, total_individual: int, total_corporate: int, total: int}>
     */
    private function resolveItems(): array
    {
        return array_values(array_filter(
            $this->resolvePayload()['items'],
            static fn (array $item): bool => $item['total'] > 0,
        ));
    }

    /**
     * @return array{
     *     items: list<array{agent_id: int, agent_name: string, code_agent: string|null, total_individual: int, total_corporate: int, total: int}>,
     *     total_affiliations: int,
     *     total_individual_affiliations: int,
     *     total_corporate_affiliations: int,
     *     total_agents: int,
     *     limit: int
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(CorretajeAgentsMetricsClient::class)->byActiveAffiliations(20);
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de afiliaciones activas por agente desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'items' => [],
                'total_affiliations' => 0,
                'total_individual_affiliations' => 0,
                'total_corporate_affiliations' => 0,
                'total_agents' => 0,
                'limit' => 20,
            ];
        }

        return $this->resolvedPayload;
    }

    private function formatAgentLabel(string $name, ?string $codeAgent, bool $short): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($trimmed === '') {
            $trimmed = 'Sin nombre';
        }

        $label = $codeAgent !== null && $codeAgent !== ''
            ? "{$codeAgent} · {$trimmed}"
            : $trimmed;

        if (! $short) {
            return $label;
        }

        return (string) Str::limit($label, 22, '…');
    }
}
