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

class CorretajeAgentsByStateChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '28rem';

    protected string $view = 'filament.metrics.widgets.corretaje-agents-by-state-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Agentes activos por estado';

    protected ?string $description = 'Solo estatus ACTIVO · distribución geográfica en tiempo real.';

    /**
     * @var array{items: list<array{state_id: int|null, state: string, total: int}>, total_active: int}|null
     */
    private ?array $resolvedPayload = null;

    /**
     * Paleta teal→sky coherente con el panel Métricas (menos ruido visual que rainbow).
     *
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
        return 'Sin agentes activos por estado';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay agentes con estatus ACTIVO para agrupar por estado.';
    }

    public function getChartTotalActive(): int
    {
        return (int) ($this->resolvePayload()['total_active'] ?? 0);
    }

    public function getChartStatesCount(): int
    {
        return count($this->resolveItems());
    }

    public function getChartTopStateLabel(): string
    {
        $items = $this->resolveItems();
        $top = $items[0] ?? null;

        if ($top === null) {
            return '—';
        }

        return $this->formatStateLabel($top['state']);
    }

    public function getChartTopStateTotal(): int
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
                $this->getChartTotalActive(),
                $this->getChartStatesCount(),
                $this->getChartTopStateTotal(),
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-agents-by-state-'.$fingerprint;
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
                        'label' => 'Agentes activos',
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
                    ],
                ],
            ];
        }

        $max = max(1, max(array_column($items, 'total')));
        $labels = [];
        $values = [];
        $fills = [];
        $hovers = [];
        $borders = [];

        foreach ($items as $index => $item) {
            $labels[] = $this->formatStateLabel($item['state']);
            $values[] = $item['total'];

            $intensity = 0.55 + (0.45 * ($item['total'] / $max));
            [$r, $g, $b] = self::BAR_RGB[$index % count(self::BAR_RGB)];
            $fills[] = sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $intensity);
            $hovers[] = sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, min(1, $intensity + 0.12));
            $borders[] = 'transparent';
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Agentes activos',
                    'data' => $values,
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
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return '';
                    return ' ' + Number(v).toLocaleString('es-VE') + ' agentes activos';
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
     * @return list<array{state_id: int|null, state: string, total: int}>
     */
    private function resolveItems(): array
    {
        return array_values(array_filter(
            $this->resolvePayload()['items'],
            static fn (array $item): bool => $item['total'] > 0,
        ));
    }

    /**
     * @return array{items: list<array{state_id: int|null, state: string, total: int}>, total_active: int}
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(CorretajeAgentsMetricsClient::class)->byState();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de agentes activos por estado desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'items' => [],
                'total_active' => 0,
            ];
        }

        return $this->resolvedPayload;
    }

    private function formatStateLabel(string $state): string
    {
        $trimmed = trim($state);

        if ($trimmed === '' || strcasecmp($trimmed, 'Sin estado') === 0) {
            return 'Sin estado';
        }

        return Str::title(mb_strtolower($trimmed));
    }
}
