<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\HasMetricsChartPerformance;
use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Services\IntegracorpApi\AfiliacionesMetricsClient;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Log;
use Throwable;

class AfiliacionesByStatePieChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '26rem';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.metrics.widgets.afiliaciones-by-state-pie-chart';

    protected ?string $heading = 'Afiliaciones por estado';

    protected ?string $description = 'Activas · individuales + corporativas · top estados y Otros.';

    /**
     * Colores vivos (misma línea visual que las tortas por plan).
     *
     * @var list<string>
     */
    private const STATE_COLORS = [
        '#2F6BFF',
        '#2ECC71',
        '#FF8A00',
        '#E74C3C',
        '#9B59B6',
        '#00B8D9',
        '#F4C430',
        '#FF5CA8',
        '#1ABC9C',
        '#8E44AD',
        '#95A5A6',
    ];

    /**
     * @var array{
     *     scope: string,
     *     metric: string,
     *     states: list<array{state_id: int|null, label: string, count: int, is_other: bool}>,
     *     total_count: int,
     *     states_count: int,
     *     states_shown: int,
     *     others_count: int,
     *     top_state: array{state_id: int|null, label: string, count: int}|null
     * }|null
     */
    private ?array $resolvedPayload = null;

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getChartTotalCount(): int
    {
        return (int) ($this->resolvePayload()['total_count'] ?? 0);
    }

    public function getChartStatesCount(): int
    {
        return (int) ($this->resolvePayload()['states_count'] ?? 0);
    }

    public function getTopStateLabel(): string
    {
        return (string) ($this->resolvePayload()['top_state']['label'] ?? '—');
    }

    public function getTopStateCountLabel(): string
    {
        return number_format((int) ($this->resolvePayload()['top_state']['count'] ?? 0), 0, ',', '.');
    }

    public function getMaxHeight(): ?string
    {
        return '520px';
    }

    public function getIosBarChartWireKey(): string
    {
        $fingerprint = hash(
            'xxh128',
            json_encode([
                $this->resolvePayload()['total_count'] ?? 0,
                $this->resolvePayload()['states_count'] ?? 0,
                $this->resolvePayload()['top_state']['count'] ?? 0,
                $this->resolvePayload()['others_count'] ?? 0,
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-afiliaciones-by-state-pie-'.$fingerprint;
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin afiliaciones por estado';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay afiliaciones activas individuales o corporativas para agrupar por estado.';
    }

    protected function metricsChartPlaceholderHeading(): string
    {
        return (string) $this->heading;
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $states = $this->resolvePayload()['states'];
        $labels = [];
        $counts = [];
        $colors = [];
        $hoverColors = [];
        $colorIndex = 0;

        foreach ($states as $state) {
            $count = (int) $state['count'];
            if ($count <= 0) {
                continue;
            }

            $labels[] = $state['label'];
            $counts[] = $count;

            if ($state['is_other']) {
                $color = self::STATE_COLORS[count(self::STATE_COLORS) - 1];
            } else {
                $color = self::STATE_COLORS[$colorIndex % (count(self::STATE_COLORS) - 1)];
                $colorIndex++;
            }

            $colors[] = $color;
            $hoverColors[] = $color;
        }

        if ($labels === []) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Afiliaciones',
                        'valueFormat' => 'count',
                        'data' => [],
                        'backgroundColor' => [],
                        'borderWidth' => 0,
                    ],
                ],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Afiliaciones',
                    'valueFormat' => 'count',
                    'data' => $counts,
                    'backgroundColor' => $colors,
                    'hoverBackgroundColor' => $hoverColors,
                    'borderColor' => array_fill(0, count($counts), 'rgba(255, 255, 255, 0.92)'),
                    'borderWidth' => 4,
                    'hoverOffset' => 12,
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
    cutout: 0,
    layout: {
        padding: { top: 64, right: 180, bottom: 64, left: 180 }
    },
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            enabled: true,
            backgroundColor: 'rgba(15, 23, 42, 0.92)',
            titleColor: 'rgba(255, 255, 255, 0.98)',
            bodyColor: 'rgba(226, 232, 240, 0.95)',
            borderColor: 'rgba(255, 255, 255, 0.18)',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            boxPadding: 6,
            cornerRadius: 12,
            titleFont: { weight: '700', size: 12 },
            bodyFont: { size: 12 },
            callbacks: {
                label: (ctx) => {
                    const value = Number(ctx.parsed ?? 0);
                    const data = Array.isArray(ctx.dataset?.data) ? ctx.dataset.data : [];
                    const total = data.reduce((sum, item) => sum + Number(item || 0), 0);
                    const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                    const name = ctx.label || 'Estado';
                    return ' ' + name + ': ' + Math.round(value).toLocaleString('es-VE') + ' afil. (' + pct + '%)';
                }
            }
        }
    },
    animation: {
        duration: 420,
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
     * @return array{
     *     scope: string,
     *     metric: string,
     *     states: list<array{state_id: int|null, label: string, count: int, is_other: bool}>,
     *     total_count: int,
     *     states_count: int,
     *     states_shown: int,
     *     others_count: int,
     *     top_state: array{state_id: int|null, label: string, count: int}|null
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(AfiliacionesMetricsClient::class)->byState();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de afiliaciones por estado desde integracorp-api.', [
                'widget' => static::class,
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'scope' => 'active_stock',
                'metric' => 'count',
                'states' => [],
                'total_count' => 0,
                'states_count' => 0,
                'states_shown' => 0,
                'others_count' => 0,
                'top_state' => null,
            ];
        }

        return $this->resolvedPayload;
    }
}
