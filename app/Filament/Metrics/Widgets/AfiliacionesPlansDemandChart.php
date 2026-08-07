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

class AfiliacionesPlansDemandChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '28rem';

    protected string $view = 'filament.metrics.widgets.afiliaciones-plans-demand-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Demanda por plan a lo largo del año';

    protected ?string $description = 'Tres líneas: Plan Inicial, Plan Ideal y Plan Especial. Suma afiliaciones individuales y corporativas del año en curso, para ver cuál tiene más demanda y cuál menos.';

    /**
     * @var array{
     *     scope: string,
     *     year: int,
     *     through_month: int,
     *     labels: list<string>,
     *     plans: list<array{plan_id: int, code: string, label: string, values: list<int>, total: int}>,
     *     total: int,
     *     most_demanded: array{plan_id: int, label: string, total: int}|null,
     *     least_demanded: array{plan_id: int, label: string, total: int}|null
     * }|null
     */
    private ?array $resolvedPayload = null;

    protected function metricsChartPlaceholderHeading(): string
    {
        return (string) $this->heading;
    }

    public function getChartYear(): int
    {
        return (int) ($this->resolvePayload()['year'] ?? now()->year);
    }

    public function getChartTotal(): int
    {
        return (int) ($this->resolvePayload()['total'] ?? 0);
    }

    public function getMostDemandedLabel(): string
    {
        return (string) ($this->resolvePayload()['most_demanded']['label'] ?? '—');
    }

    public function getMostDemandedTotal(): int
    {
        return (int) ($this->resolvePayload()['most_demanded']['total'] ?? 0);
    }

    public function getLeastDemandedLabel(): string
    {
        return (string) ($this->resolvePayload()['least_demanded']['label'] ?? '—');
    }

    public function getLeastDemandedTotal(): int
    {
        return (int) ($this->resolvePayload()['least_demanded']['total'] ?? 0);
    }

    public function getMaxHeight(): ?string
    {
        return '380px';
    }

    public function getIosBarChartWireKey(): string
    {
        $fingerprint = hash(
            'xxh128',
            json_encode([
                $this->getChartYear(),
                $this->getChartTotal(),
                $this->getMostDemandedTotal(),
                $this->getLeastDemandedTotal(),
                $this->resolvePayload()['through_month'] ?? 0,
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-afiliaciones-plans-demand-'.$fingerprint;
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin demanda por plan para graficar';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'Todavía no hay afiliaciones individuales o corporativas con Plan Inicial, Ideal o Especial en el año en curso.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $payload = $this->resolvePayload();
        $labels = $payload['labels'];
        $palettes = [
            1 => [
                'border' => 'rgba(14, 165, 233, 1)',
                'background' => 'rgba(14, 165, 233, 0.12)',
                'point' => 'rgba(56, 189, 248, 1)',
            ],
            2 => [
                'border' => 'rgba(16, 185, 129, 1)',
                'background' => 'rgba(16, 185, 129, 0.12)',
                'point' => 'rgba(52, 211, 153, 1)',
            ],
            3 => [
                'border' => 'rgba(245, 158, 11, 1)',
                'background' => 'rgba(245, 158, 11, 0.12)',
                'point' => 'rgba(251, 191, 36, 1)',
            ],
        ];

        $datasets = [];
        foreach ($payload['plans'] as $plan) {
            $palette = $palettes[$plan['plan_id']] ?? $palettes[1];

            $datasets[] = [
                'label' => $plan['label'],
                'data' => $plan['values'],
                'borderColor' => $palette['border'],
                'backgroundColor' => $palette['background'],
                'pointBackgroundColor' => $palette['point'],
                'pointBorderColor' => $palette['border'],
                'fill' => false,
                'tension' => 0.35,
                'pointRadius' => 3.5,
                'pointHoverRadius' => 6,
                'borderWidth' => 2.75,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
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
        padding: { top: 16, right: 12, bottom: 4, left: 4 }
    },
    interaction: {
        mode: 'index',
        intersect: false
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 16,
                color: '#334155',
                font: { size: 12, weight: '600' }
            }
        },
        tooltip: {
            enabled: true,
            backgroundColor: 'rgba(15, 23, 42, 0.84)',
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
                    const name = ctx.dataset?.label || 'Plan';
                    return ' ' + name + ': ' + Number(v).toLocaleString('es-VE') + ' afiliaciones';
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
                font: { size: 11, weight: '700' },
                padding: 6
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
        duration: 320,
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
     *     year: int,
     *     through_month: int,
     *     labels: list<string>,
     *     plans: list<array{plan_id: int, code: string, label: string, values: list<int>, total: int}>,
     *     total: int,
     *     most_demanded: array{plan_id: int, label: string, total: int}|null,
     *     least_demanded: array{plan_id: int, label: string, total: int}|null
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(AfiliacionesMetricsClient::class)->byPlanMonth();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar la demanda por plan desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $now = now();
            $this->resolvedPayload = [
                'scope' => 'combined',
                'year' => (int) $now->year,
                'through_month' => (int) $now->month,
                'labels' => [],
                'plans' => [],
                'total' => 0,
                'most_demanded' => null,
                'least_demanded' => null,
            ];
        }

        return $this->resolvedPayload;
    }
}
