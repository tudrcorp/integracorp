<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\HasMetricsChartPerformance;
use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Services\IntegracorpApi\CorretajeAgenciesMetricsClient;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CorretajeAgenciesByStateChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '28rem';

    protected string $view = 'filament.metrics.widgets.corretaje-agencies-by-state-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Agencias activas MASTER y GENERAL por estado';

    protected ?string $description = 'Solo estatus ACTIVO · dos barras por estado (MASTER vs GENERAL).';

    /**
     * @var array{
     *     items: list<array{state_id: int|null, state: string, total_masters: int, total_generals: int, total: int}>,
     *     total_active: int,
     *     total_masters: int,
     *     total_generals: int
     * }|null
     */
    private ?array $resolvedPayload = null;

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin agencias activas por estado';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay agencias MASTER o GENERAL con estatus ACTIVO para agrupar por estado.';
    }

    public function getChartTotalActive(): int
    {
        return (int) ($this->resolvePayload()['total_active'] ?? 0);
    }

    public function getChartTotalMasters(): int
    {
        return (int) ($this->resolvePayload()['total_masters'] ?? 0);
    }

    public function getChartTotalGenerals(): int
    {
        return (int) ($this->resolvePayload()['total_generals'] ?? 0);
    }

    public function getChartStatesCount(): int
    {
        return count($this->resolveItems());
    }

    public function getChartTopStateLabel(): string
    {
        $top = $this->resolveItems()[0] ?? null;

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
                $this->getChartTotalMasters(),
                $this->getChartTotalGenerals(),
                $this->getChartStatesCount(),
                $this->getChartTopStateTotal(),
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-agencies-by-state-'.$fingerprint;
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
                        'label' => 'MASTER',
                        'data' => [],
                        'backgroundColor' => 'rgba(14, 165, 233, 0.82)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                    ],
                    [
                        'label' => 'GENERAL',
                        'data' => [],
                        'backgroundColor' => 'rgba(124, 58, 237, 0.82)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                    ],
                ],
            ];
        }

        return [
            'labels' => array_map(
                fn (array $item): string => $this->formatStateLabel($item['state']),
                $items,
            ),
            'datasets' => [
                [
                    'label' => 'MASTER',
                    'data' => array_column($items, 'total_masters'),
                    'backgroundColor' => 'rgba(14, 165, 233, 0.82)',
                    'hoverBackgroundColor' => 'rgba(14, 165, 233, 0.95)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 44,
                ],
                [
                    'label' => 'GENERAL',
                    'data' => array_column($items, 'total_generals'),
                    'backgroundColor' => 'rgba(124, 58, 237, 0.82)',
                    'hoverBackgroundColor' => 'rgba(124, 58, 237, 0.95)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 44,
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
            categoryPercentage: 0.86,
            barPercentage: 0.95
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
                boxWidth: 12,
                boxHeight: 12,
                borderRadius: 4,
                usePointStyle: true,
                pointStyle: 'rectRounded',
                color: '#94a3b8',
                font: { size: 11, weight: '600' },
                padding: 14
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
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return '';
                    const series = ctx.dataset?.label || 'Agencias';
                    return ' ' + series + ': ' + Number(v).toLocaleString('es-VE');
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
     * @return list<array{state_id: int|null, state: string, total_masters: int, total_generals: int, total: int}>
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
     *     items: list<array{state_id: int|null, state: string, total_masters: int, total_generals: int, total: int}>,
     *     total_active: int,
     *     total_masters: int,
     *     total_generals: int
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(CorretajeAgenciesMetricsClient::class)->byState();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de agencias activas por estado desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'items' => [],
                'total_active' => 0,
                'total_masters' => 0,
                'total_generals' => 0,
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
