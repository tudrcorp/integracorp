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

class CorretajeAgentsSalesByStateRadarChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '32rem';

    protected string $view = 'filament.metrics.widgets.corretaje-agents-sales-by-state-radar-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Radar · tendencia de ventas US$ por estado';

    protected ?string $description = 'Suma de total_amount de afiliaciones ACTIVA según el estado del agente · 24 estados.';

    /**
     * @var array{
     *     items: list<array{state_id: int, state: string, affiliations_count: int, total_amount: float}>,
     *     total_affiliations: int,
     *     total_agents: int,
     *     total_amount: float,
     *     states_count: int,
     *     top_state: array{state_id: int, state: string, affiliations_count: int, total_amount: float}|null
     * }|null
     */
    private ?array $resolvedPayload = null;

    /**
     * Paleta vibrante por vértice del radar (uno por estado).
     *
     * @var list<string>
     */
    private const POINT_COLORS = [
        '#FF2D55',
        '#FF9500',
        '#FFCC00',
        '#34C759',
        '#00C7BE',
        '#32ADE6',
        '#007AFF',
        '#5856D6',
        '#AF52DE',
        '#FF375F',
        '#FF9F0A',
        '#30D158',
        '#64D2FF',
        '#5E5CE6',
        '#BF5AF2',
        '#FF6482',
        '#FFD60A',
        '#66D4CF',
        '#0A84FF',
        '#5AC8FA',
        '#FF453A',
        '#30B0C7',
        '#AC8E68',
        '#8E8E93',
    ];

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin tendencia de ventas por estado';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay afiliaciones ACTIVA con agent_id para calcular ventas US$ por estado.';
    }

    public function getChartTotalAmountFormatted(): string
    {
        return number_format($this->getChartTotalAmount(), 2, ',', '.');
    }

    public function getChartTotalAmount(): float
    {
        return (float) ($this->resolvePayload()['total_amount'] ?? 0);
    }

    public function getChartStatesCount(): int
    {
        return (int) ($this->resolvePayload()['states_count'] ?? count($this->resolveItems()));
    }

    public function getChartTopStateLabel(): string
    {
        $top = $this->resolvePayload()['top_state'] ?? null;

        if ($top === null) {
            return '—';
        }

        return $this->formatStateLabel($top['state']);
    }

    public function getChartTopStateAmountFormatted(): string
    {
        $top = $this->resolvePayload()['top_state'] ?? null;

        return number_format((float) ($top['total_amount'] ?? 0), 2, ',', '.');
    }

    public function getMaxHeight(): ?string
    {
        return '480px';
    }

    public function getIosBarChartWireKey(): string
    {
        $top = $this->resolvePayload()['top_state'] ?? null;
        $fingerprint = hash(
            'xxh128',
            json_encode([
                $this->getChartTotalAmount(),
                $this->getChartStatesCount(),
                $top['total_amount'] ?? 0,
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-agents-sales-by-state-radar-'.$fingerprint;
    }

    protected function getType(): string
    {
        return 'radar';
    }

    protected function getData(): array
    {
        $items = $this->resolveItems();

        if ($items === []) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Ventas US$',
                        'data' => [],
                        'fill' => true,
                        'backgroundColor' => 'rgba(88, 86, 214, 0.18)',
                        'borderColor' => 'rgba(88, 86, 214, 0.85)',
                        'pointBackgroundColor' => [],
                        'borderWidth' => 2,
                    ],
                ],
            ];
        }

        $max = max(1.0, max(array_column($items, 'total_amount')));
        $labels = [];
        $values = [];
        $pointColors = [];
        $pointRadii = [];
        $affiliationCounts = [];

        foreach ($items as $index => $item) {
            $labels[] = $this->formatStateLabel($item['state']);
            $values[] = $item['total_amount'];
            $affiliationCounts[] = $item['affiliations_count'];
            $pointColors[] = self::POINT_COLORS[$index % count(self::POINT_COLORS)];
            $pointRadii[] = round(3.2 + (5.5 * ($item['total_amount'] / $max)), 1);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Ventas US$',
                    'data' => $values,
                    'affiliationsCount' => $affiliationCounts,
                    'fill' => true,
                    'backgroundColor' => 'rgba(88, 86, 214, 0.16)',
                    'borderColor' => 'rgba(10, 132, 255, 0.88)',
                    'borderWidth' => 2.4,
                    'pointBackgroundColor' => $pointColors,
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1.5,
                    'pointHoverBackgroundColor' => '#ffffff',
                    'pointHoverBorderColor' => $pointColors,
                    'pointRadius' => $pointRadii,
                    'pointHoverRadius' => array_map(
                        static fn (float $radius): float => $radius + 2.2,
                        $pointRadii,
                    ),
                    'tension' => 0.12,
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
    devicePixelRatio: Math.min(window.devicePixelRatio || 1, 1.5),
    layout: {
        padding: { top: 10, right: 18, bottom: 10, left: 18 }
    },
    interaction: {
        mode: 'nearest',
        intersect: false
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            enabled: true,
            backgroundColor: 'rgba(15, 23, 42, 0.86)',
            titleColor: 'rgba(255, 255, 255, 0.96)',
            bodyColor: 'rgba(226, 232, 240, 0.92)',
            borderColor: 'rgba(168, 85, 247, 0.45)',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            boxPadding: 6,
            cornerRadius: 10,
            titleFont: { weight: '600', size: 12 },
            bodyFont: { size: 12 },
            callbacks: {
                label: (ctx) => {
                    const amount = ctx.parsed?.r;
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
        r: {
            beginAtZero: true,
            suggestedMin: 0,
            ticks: {
                color: '#64748b',
                backdropColor: 'transparent',
                showLabelBackdrop: false,
                maxTicksLimit: 5,
                font: { size: 10, weight: '500' },
                callback: (value) => 'US$ ' + Number(value).toLocaleString('es-VE')
            },
            grid: {
                color: 'rgba(100, 116, 139, 0.16)'
            },
            angleLines: {
                color: 'rgba(100, 116, 139, 0.14)'
            },
            pointLabels: {
                color: '#334155',
                font: { size: 9.5, weight: '600' },
                padding: 6
            }
        }
    },
    animation: {
        duration: 280,
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
     * @return list<array{state_id: int, state: string, affiliations_count: int, total_amount: float}>
     */
    private function resolveItems(): array
    {
        return $this->resolvePayload()['items'];
    }

    /**
     * @return array{
     *     items: list<array{state_id: int, state: string, affiliations_count: int, total_amount: float}>,
     *     total_affiliations: int,
     *     total_agents: int,
     *     total_amount: float,
     *     states_count: int,
     *     top_state: array{state_id: int, state: string, affiliations_count: int, total_amount: float}|null
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(CorretajeAgentsMetricsClient::class)->salesByState();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el radar de ventas US$ por estado desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'items' => [],
                'total_affiliations' => 0,
                'total_agents' => 0,
                'total_amount' => 0.0,
                'states_count' => 0,
                'top_state' => null,
            ];
        }

        return $this->resolvedPayload;
    }

    private function formatStateLabel(string $state): string
    {
        $trimmed = trim($state);

        if ($trimmed === '') {
            return 'Sin estado';
        }

        return (string) Str::limit(Str::title(mb_strtolower($trimmed)), 14, '…');
    }
}
