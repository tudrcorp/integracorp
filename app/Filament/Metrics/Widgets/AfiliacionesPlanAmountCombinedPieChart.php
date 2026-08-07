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

class AfiliacionesPlanAmountCombinedPieChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '24rem';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.metrics.widgets.afiliaciones-plan-amount-combined-pie-chart';

    protected ?string $heading = 'Total US$ por tipo y plan';

    protected ?string $description = 'Afiliaciones activas · cuota en dólares, separada en individuales y corporativas.';

    /**
     * Colores vivos: familia azul/verde/naranja para individuales;
     * familia violeta/teal/rojo para corporativas (se distingue el tipo de un vistazo).
     *
     * @var array<string, array<int, string>>
     */
    private const SEGMENT_COLORS = [
        'individual' => [
            1 => '#2F6BFF',
            2 => '#2ECC71',
            3 => '#FF8A00',
        ],
        'corporate' => [
            1 => '#7C3AED',
            2 => '#14B8A6',
            3 => '#E11D48',
        ],
    ];

    /**
     * @var array{
     *     currency: string,
     *     scope: string,
     *     segments: list<array{kind: string, kind_label: string, plan_id: int, code: string, plan_label: string, label: string, amount: float, count: int}>,
     *     by_kind: array{individual: array{total_amount: float, total_count: int}, corporate: array{total_amount: float, total_count: int}},
     *     total_amount: float,
     *     total_count: int,
     *     top_segment: array{kind: string, kind_label: string, plan_id: int, plan_label: string, label: string, amount: float}|null
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

    public function getChartTotalAmountLabel(): string
    {
        return $this->formatUsd((float) ($this->resolvePayload()['total_amount'] ?? 0));
    }

    public function getChartTotalCount(): int
    {
        return (int) ($this->resolvePayload()['total_count'] ?? 0);
    }

    public function getIndividualAmountLabel(): string
    {
        return $this->formatUsd((float) ($this->resolvePayload()['by_kind']['individual']['total_amount'] ?? 0));
    }

    public function getIndividualCount(): int
    {
        return (int) ($this->resolvePayload()['by_kind']['individual']['total_count'] ?? 0);
    }

    public function getCorporateAmountLabel(): string
    {
        return $this->formatUsd((float) ($this->resolvePayload()['by_kind']['corporate']['total_amount'] ?? 0));
    }

    public function getCorporateCount(): int
    {
        return (int) ($this->resolvePayload()['by_kind']['corporate']['total_count'] ?? 0);
    }

    public function getTopSegmentLabel(): string
    {
        return (string) ($this->resolvePayload()['top_segment']['label'] ?? '—');
    }

    public function getTopSegmentAmountLabel(): string
    {
        return $this->formatUsd((float) ($this->resolvePayload()['top_segment']['amount'] ?? 0));
    }

    public function getMaxHeight(): ?string
    {
        return '400px';
    }

    public function getIosBarChartWireKey(): string
    {
        $fingerprint = hash(
            'xxh128',
            json_encode([
                $this->resolvePayload()['total_amount'] ?? 0,
                $this->resolvePayload()['by_kind']['individual']['total_amount'] ?? 0,
                $this->resolvePayload()['by_kind']['corporate']['total_amount'] ?? 0,
                $this->resolvePayload()['top_segment']['amount'] ?? 0,
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-afiliaciones-plan-amount-combined-pie-'.$fingerprint;
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin montos US$ para graficar';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay afiliaciones activas individuales o corporativas con Plan Inicial, Ideal o Especial.';
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
        $segments = $this->resolvePayload()['segments'];
        $labels = [];
        $amounts = [];
        $colors = [];
        $hoverColors = [];

        foreach ($segments as $segment) {
            $amount = (float) $segment['amount'];
            if ($amount <= 0) {
                continue;
            }

            $kind = $segment['kind'] === 'corporate' ? 'corporate' : 'individual';
            $planId = (int) $segment['plan_id'];
            $color = self::SEGMENT_COLORS[$kind][$planId] ?? '#5AC8FA';

            $labels[] = $segment['label'];
            $amounts[] = round($amount, 2);
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
        padding: { top: 40, right: 96, bottom: 40, left: 96 }
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
                    const name = ctx.label || 'Segmento';
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
     *     currency: string,
     *     scope: string,
     *     segments: list<array{kind: string, kind_label: string, plan_id: int, code: string, plan_label: string, label: string, amount: float, count: int}>,
     *     by_kind: array{individual: array{total_amount: float, total_count: int}, corporate: array{total_amount: float, total_count: int}},
     *     total_amount: float,
     *     total_count: int,
     *     top_segment: array{kind: string, kind_label: string, plan_id: int, plan_label: string, label: string, amount: float}|null
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(AfiliacionesMetricsClient::class)->byPlanAmountCombined();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico combinado de montos US$ por tipo/plan.', [
                'widget' => static::class,
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'currency' => 'USD',
                'scope' => 'active_stock',
                'segments' => [],
                'by_kind' => [
                    'individual' => ['total_amount' => 0.0, 'total_count' => 0],
                    'corporate' => ['total_amount' => 0.0, 'total_count' => 0],
                ],
                'total_amount' => 0.0,
                'total_count' => 0,
                'top_segment' => null,
            ];
        }

        return $this->resolvedPayload;
    }
}
