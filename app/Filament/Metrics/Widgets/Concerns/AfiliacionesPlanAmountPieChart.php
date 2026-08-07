<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Concerns;

use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Services\IntegracorpApi\AfiliacionesMetricsClient;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AfiliacionesPlanAmountPieChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '26rem';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.metrics.widgets.afiliaciones-plan-amount-pie-chart';

    /**
     * Colores vivos para que cada plan se distinga de un vistazo.
     *
     * @var array<int, string>
     */
    private const PLAN_COLORS = [
        1 => '#2F6BFF', // Plan Inicial — azul intenso
        2 => '#2ECC71', // Plan Ideal — verde vivo
        3 => '#FF8A00', // Plan Especial — naranja brillante
    ];

    /**
     * @var array{
     *     kind: string,
     *     currency: string,
     *     scope: string,
     *     plans: list<array{plan_id: int, code: string, label: string, amount: float, count: int}>,
     *     total_amount: float,
     *     total_count: int,
     *     top_plan: array{plan_id: int, label: string, amount: float}|null
     * }|null
     */
    private ?array $resolvedPayload = null;

    abstract protected function affiliationKind(): string;

    abstract protected function overviewHeading(): string;

    abstract protected function overviewDescription(): string;

    protected function chartWireKeyPrefix(): string
    {
        return 'metrics-afiliaciones-'.$this->affiliationKind().'-plan-amount-pie';
    }

    protected function metricsChartPlaceholderHeading(): string
    {
        return $this->overviewHeading();
    }

    public function getHeading(): ?string
    {
        return $this->overviewHeading();
    }

    public function getDescription(): ?string
    {
        return $this->overviewDescription();
    }

    public function getChartTotalAmountLabel(): string
    {
        return $this->formatUsd((float) ($this->resolvePayload()['total_amount'] ?? 0));
    }

    public function getChartTotalCount(): int
    {
        return (int) ($this->resolvePayload()['total_count'] ?? 0);
    }

    public function getTopPlanLabel(): string
    {
        return (string) ($this->resolvePayload()['top_plan']['label'] ?? '—');
    }

    public function getTopPlanAmountLabel(): string
    {
        return $this->formatUsd((float) ($this->resolvePayload()['top_plan']['amount'] ?? 0));
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
                $this->affiliationKind(),
                $this->resolvePayload()['total_amount'] ?? 0,
                $this->resolvePayload()['total_count'] ?? 0,
                $this->resolvePayload()['top_plan']['amount'] ?? 0,
            ], JSON_THROW_ON_ERROR),
        );

        return $this->chartWireKeyPrefix().'-'.$fingerprint;
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin montos US$ para graficar';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay afiliaciones activas con Plan Inicial, Ideal o Especial para mostrar el total en dólares.';
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $plans = $this->resolvePayload()['plans'];
        $labels = [];
        $amounts = [];
        $colors = [];
        $hoverColors = [];

        foreach ($plans as $plan) {
            $amount = (float) $plan['amount'];
            if ($amount <= 0) {
                continue;
            }

            $labels[] = $plan['label'];
            $amounts[] = round($amount, 2);
            $color = self::PLAN_COLORS[$plan['plan_id']] ?? '#5AC8FA';
            $colors[] = $color;
            $hoverColors[] = $color;
        }

        if ($labels === []) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'US$',
                        'valueFormat' => 'usd',
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
                    'label' => 'US$',
                    'valueFormat' => 'usd',
                    'data' => $amounts,
                    'backgroundColor' => $colors,
                    'hoverBackgroundColor' => $hoverColors,
                    'borderColor' => array_fill(0, count($amounts), 'rgba(255, 255, 255, 0.92)'),
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
        padding: { top: 36, right: 72, bottom: 36, left: 72 }
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
                    const name = ctx.label || 'Plan';
                    return ' ' + name + ': US$ ' + value.toLocaleString('es-VE', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' (' + pct + '%)';
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

    private function formatUsd(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    /**
     * @return array{
     *     kind: string,
     *     currency: string,
     *     scope: string,
     *     plans: list<array{plan_id: int, code: string, label: string, amount: float, count: int}>,
     *     total_amount: float,
     *     total_count: int,
     *     top_plan: array{plan_id: int, label: string, amount: float}|null
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(AfiliacionesMetricsClient::class)->byPlanAmount($this->affiliationKind());
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de montos US$ por plan desde integracorp-api.', [
                'widget' => static::class,
                'kind' => $this->affiliationKind(),
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'kind' => $this->affiliationKind(),
                'currency' => 'USD',
                'scope' => 'active_stock',
                'plans' => [],
                'total_amount' => 0.0,
                'total_count' => 0,
                'top_plan' => null,
            ];
        }

        return $this->resolvedPayload;
    }
}
