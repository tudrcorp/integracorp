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

class CorretajeAgentsByActiveAffiliationAmountChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '28rem';

    protected string $view = 'filament.metrics.widgets.corretaje-agents-by-active-affiliation-amount-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Monto US$ de afiliaciones activas por agente';

    protected ?string $description = 'Suma de total_amount · solo ACTIVA con agent_id · top 20.';

    /**
     * @var array{
     *     items: list<array{agent_id: int, agent_name: string, code_agent: string|null, affiliations_count: int, total_amount: float}>,
     *     total_affiliations: int,
     *     total_agents: int,
     *     total_amount: float,
     *     limit: int
     * }|null
     */
    private ?array $resolvedPayload = null;

    /**
     * @var list<array{0: int, 1: int, 2: int}>
     */
    private const BAR_RGB = [
        [13, 148, 136],
        [14, 165, 233],
        [20, 184, 166],
        [56, 189, 248],
        [45, 212, 191],
        [2, 132, 199],
        [15, 118, 110],
        [125, 211, 252],
    ];

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin montos de afiliaciones activas';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay afiliaciones ACTIVA con agent_id y total_amount para graficar.';
    }

    public function getChartTotalAmountFormatted(): string
    {
        return number_format($this->getChartTotalAmount(), 2, ',', '.');
    }

    public function getChartTotalAmount(): float
    {
        return (float) ($this->resolvePayload()['total_amount'] ?? 0);
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

    public function getChartTopAgentAmountFormatted(): string
    {
        return number_format($this->getChartTopAgentAmount(), 2, ',', '.');
    }

    public function getChartTopAgentAmount(): float
    {
        return (float) (($this->resolveItems()[0]['total_amount'] ?? 0));
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
                $this->getChartTotalAmount(),
                $this->getChartAgentsCount(),
                $this->getChartTopAgentAmount(),
                count($this->resolveItems()),
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-agents-by-active-affiliation-amount-'.$fingerprint;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $items = $this->resolveItems();

        if ($items === []) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Total US$',
                        'data' => [],
                        'backgroundColor' => [],
                        'borderColor' => [],
                        'hoverBackgroundColor' => [],
                        'borderWidth' => 0,
                        'borderRadius' => [
                            'topLeft' => 10,
                            'topRight' => 10,
                            'bottomLeft' => 0,
                            'bottomRight' => 0,
                        ],
                        'borderSkipped' => false,
                        'valueLabelFormat' => 'usd',
                    ],
                ],
            ];
        }

        $max = max(1.0, max(array_column($items, 'total_amount')));
        $labels = [];
        $values = [];
        $fills = [];
        $hovers = [];
        $borders = [];
        $fullNames = [];
        $affiliationCounts = [];

        foreach ($items as $index => $item) {
            $labels[] = $this->formatAgentLabel($item['agent_name'], $item['code_agent'], short: true);
            $fullNames[] = $this->formatAgentLabel($item['agent_name'], $item['code_agent'], short: false);
            $values[] = $item['total_amount'];
            $affiliationCounts[] = $item['affiliations_count'];

            $intensity = 0.55 + (0.45 * ($item['total_amount'] / $max));
            [$r, $g, $b] = self::BAR_RGB[$index % count(self::BAR_RGB)];
            $fills[] = sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $intensity);
            $hovers[] = sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, min(1, $intensity + 0.12));
            $borders[] = 'transparent';
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total US$',
                    'data' => $values,
                    'fullNames' => $fullNames,
                    'affiliationsCount' => $affiliationCounts,
                    'backgroundColor' => $fills,
                    'hoverBackgroundColor' => $hovers,
                    'borderColor' => $borders,
                    'borderWidth' => 0,
                    'borderRadius' => [
                        'topLeft' => 10,
                        'topRight' => 10,
                        'bottomLeft' => 0,
                        'bottomRight' => 0,
                    ],
                    'borderSkipped' => false,
                    'maxBarThickness' => 52,
                    'valueLabelFormat' => 'usd',
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
        padding: { top: 36, right: 8, bottom: 4, left: 4 }
    },
    interaction: {
        mode: 'index',
        intersect: false
    },
    datasets: {
        bar: {
            categoryPercentage: 0.88,
            barPercentage: 0.94
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
        legend: { display: false },
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
            callbacks: {
                title: (items) => {
                    const first = items?.[0];
                    if (!first) return '';
                    const full = first.dataset?.fullNames?.[first.dataIndex];
                    return full || first.label || '';
                },
                label: (ctx) => {
                    const amount = ctx.parsed?.y;
                    if (amount === null || amount === undefined) return '';
                    const count = ctx.dataset?.affiliationsCount?.[ctx.dataIndex] ?? 0;
                    const amountLabel = Number(amount).toLocaleString('es-VE', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                    const countLabel = Number(count).toLocaleString('es-VE');
                    return ' US$ ' + amountLabel + ' · ' + countLabel + ' afiliaciones';
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
                font: { size: 11, weight: '500' },
                maxTicksLimit: 6,
                callback: (value) => 'US$ ' + Number(value).toLocaleString('es-VE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })
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
     * @return list<array{agent_id: int, agent_name: string, code_agent: string|null, affiliations_count: int, total_amount: float}>
     */
    private function resolveItems(): array
    {
        return array_values(array_filter(
            $this->resolvePayload()['items'],
            static fn (array $item): bool => $item['total_amount'] > 0,
        ));
    }

    /**
     * @return array{
     *     items: list<array{agent_id: int, agent_name: string, code_agent: string|null, affiliations_count: int, total_amount: float}>,
     *     total_affiliations: int,
     *     total_agents: int,
     *     total_amount: float,
     *     limit: int
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(CorretajeAgentsMetricsClient::class)->byActiveAffiliationAmount(20);
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de monto US$ de afiliaciones por agente desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'items' => [],
                'total_affiliations' => 0,
                'total_agents' => 0,
                'total_amount' => 0.0,
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
