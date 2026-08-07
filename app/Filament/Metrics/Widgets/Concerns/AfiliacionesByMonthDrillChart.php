<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Concerns;

use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Services\IntegracorpApi\AfiliacionesMetricsClient;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AfiliacionesByMonthDrillChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '26rem';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.metrics.widgets.afiliaciones-by-month-drill-chart';

    public ?int $selectedMonth = null;

    /**
     * @var array{
     *     kind: string,
     *     year: int,
     *     through_month: int,
     *     labels: list<string>,
     *     values: list<int>,
     *     total: int,
     *     peak_month: int|null,
     *     peak_label: string|null,
     *     peak_total: int
     * }|null
     */
    private ?array $resolvedMonthPayload = null;

    /**
     * @var array{
     *     kind: string,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     labels: list<string>,
     *     values: list<int>,
     *     total: int,
     *     peak_day: int|null,
     *     peak_label: string|null,
     *     peak_total: int
     * }|null
     */
    private ?array $resolvedDayPayload = null;

    abstract protected function affiliationKind(): string;

    abstract protected function overviewHeading(): string;

    abstract protected function affiliationsNoun(): string;

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    abstract protected function accentRgb(): array;

    protected function chartWireKeyPrefix(): string
    {
        return 'metrics-afiliaciones-'.$this->affiliationKind().'-by-month';
    }

    protected function metricsChartPlaceholderHeading(): string
    {
        return $this->overviewHeading();
    }

    public function isDrillDown(): bool
    {
        return is_int($this->selectedMonth)
            && $this->selectedMonth >= 1
            && $this->selectedMonth <= 12;
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->isDrillDown()) {
            return $this->overviewHeading();
        }

        return 'Detalle por día · '.$this->getSelectedMonthLabel();
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->isDrillDown()) {
            return 'Año en curso · total de '.$this->affiliationsNoun().' por mes. Clic en una barra para ver el detalle diario.';
        }

        return 'Cantidad de '.$this->affiliationsNoun().' cada día de '.$this->getSelectedMonthLabel().'. Usa Volver para regresar a la vista mensual.';
    }

    /**
     * @param  array{label?: string, index?: int}  $payload
     */
    public function handleChartClick(array $payload = []): void
    {
        if ($this->isDrillDown()) {
            return;
        }

        $index = (int) ($payload['index'] ?? -1);
        $month = $index + 1;
        $throughMonth = (int) ($this->resolveMonthPayload()['through_month'] ?? 0);

        if ($month < 1 || $month > 12 || ($throughMonth > 0 && $month > $throughMonth)) {
            return;
        }

        $this->selectedMonth = $month;
        $this->resolvedDayPayload = null;
        $this->cachedData = null;
        $this->dataChecksum = $this->generateDataChecksum();
    }

    public function resetDrillDown(): void
    {
        $this->selectedMonth = null;
        $this->resolvedDayPayload = null;
        $this->cachedData = null;
        $this->dataChecksum = $this->generateDataChecksum();
    }

    public function getChartYear(): int
    {
        return (int) ($this->resolveMonthPayload()['year'] ?? now()->year);
    }

    public function getChartTotal(): int
    {
        if ($this->isDrillDown()) {
            return (int) ($this->resolveDayPayload()['total'] ?? 0);
        }

        return (int) ($this->resolveMonthPayload()['total'] ?? 0);
    }

    public function getChartPeakLabel(): string
    {
        if ($this->isDrillDown()) {
            $label = $this->resolveDayPayload()['peak_label'] ?? null;

            return filled($label) ? 'Día '.$label : '—';
        }

        return (string) ($this->resolveMonthPayload()['peak_label'] ?? '—');
    }

    public function getChartPeakTotal(): int
    {
        if ($this->isDrillDown()) {
            return (int) ($this->resolveDayPayload()['peak_total'] ?? 0);
        }

        return (int) ($this->resolveMonthPayload()['peak_total'] ?? 0);
    }

    public function getSelectedMonthLabel(): string
    {
        if (! $this->isDrillDown()) {
            return '—';
        }

        $year = $this->getChartYear();
        $label = Carbon::create($year, (int) $this->selectedMonth, 1)
            ->locale('es')
            ->translatedFormat('F Y');

        return mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }

    public function getMaxHeight(): ?string
    {
        return $this->isDrillDown() ? '420px' : '360px';
    }

    public function getIosBarChartWireKey(): string
    {
        $fingerprint = hash(
            'xxh128',
            json_encode([
                $this->affiliationKind(),
                $this->selectedMonth,
                $this->getChartYear(),
                $this->getChartTotal(),
                $this->getChartPeakTotal(),
                $this->isDrillDown() ? $this->getChartPeakLabel() : ($this->resolveMonthPayload()['through_month'] ?? 0),
            ], JSON_THROW_ON_ERROR),
        );

        return $this->chartWireKeyPrefix().'-'.$fingerprint;
    }

    public function getIosBarChartEmptyTitle(): string
    {
        if ($this->isDrillDown()) {
            return 'Sin afiliados en '.$this->getSelectedMonthLabel();
        }

        return 'Sin '.$this->affiliationsNoun().' para graficar';
    }

    public function getIosBarChartEmptyBody(): string
    {
        if ($this->isDrillDown()) {
            return 'No se registraron '.$this->affiliationsNoun().' en ese mes.';
        }

        return 'Todavía no hay '.$this->affiliationsNoun().' registrados en el año en curso.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return $this->isDrillDown()
            ? $this->buildDayChartData()
            : $this->buildMonthChartData();
    }

    protected function getOptions(): RawJs
    {
        $unitLabel = $this->affiliationsNoun();
        $isDetail = $this->isDrillDown() ? 'true' : 'false';
        $tooltipFooter = $this->isDrillDown()
            ? 'Usa el botón Volver para regresar al gráfico mensual'
            : 'Clic para ver la cantidad por día del mes';

        return RawJs::make(<<<JS
{
    indexAxis: 'x',
    responsive: true,
    maintainAspectRatio: false,
    devicePixelRatio: Math.min(window.devicePixelRatio || 1, 1.5),
    onClick: (event, elements, chart) => {
        if ({$isDetail}) {
            return;
        }
        if (!elements || elements.length === 0) {
            return;
        }
        const index = elements[0].index;
        const label = chart?.data?.labels?.[index] ?? '';
        \$wire.handleChartClick({ label, index });
    },
    onHover: (event, elements) => {
        const target = event?.native?.target;
        if (!target) {
            return;
        }
        if ({$isDetail}) {
            target.style.cursor = 'default';
            return;
        }
        target.style.cursor = elements?.[0] ? 'pointer' : 'default';
    },
    layout: {
        padding: { top: 28, right: 8, bottom: 4, left: 4 }
    },
    interaction: {
        mode: 'index',
        intersect: false
    },
    datasets: {
        bar: {
            categoryPercentage: {$isDetail} ? 0.88 : 0.78,
            barPercentage: {$isDetail} ? 0.94 : 0.9
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
            footerColor: 'rgba(148, 163, 184, 0.95)',
            borderColor: 'rgba(45, 212, 191, 0.35)',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            boxPadding: 6,
            cornerRadius: 10,
            titleFont: { weight: '600', size: 12 },
            bodyFont: { size: 12 },
            footerFont: { size: 11, weight: '500' },
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return '';
                    return ' ' + Number(v).toLocaleString('es-VE') + ' {$unitLabel}';
                },
                footer: () => '{$tooltipFooter}'
            }
        }
    },
    scales: {
        x: {
            grid: { display: false, drawBorder: false },
            border: { display: false },
            ticks: {
                color: '#475569',
                font: { size: {$isDetail} ? 9 : 11, weight: {$isDetail} ? '600' : '700' },
                maxRotation: {$isDetail} ? 0 : 0,
                minRotation: 0,
                autoSkip: {$isDetail} ? true : false,
                maxTicksLimit: {$isDetail} ? 16 : 12,
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
     * @return array<string, mixed>
     */
    private function buildMonthChartData(): array
    {
        $payload = $this->resolveMonthPayload();
        $labels = $payload['labels'];
        $values = $payload['values'];

        return $this->buildBarDataset($labels, $values, maxBarThickness: 64);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDayChartData(): array
    {
        $payload = $this->resolveDayPayload();

        return $this->buildBarDataset($payload['labels'], $payload['values'], maxBarThickness: 28);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int>  $values
     * @return array<string, mixed>
     */
    private function buildBarDataset(array $labels, array $values, int $maxBarThickness): array
    {
        $borderRadius = [
            'topLeft' => 10,
            'topRight' => 10,
            'bottomLeft' => 0,
            'bottomRight' => 0,
        ];

        if ($labels === [] || $values === []) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => $this->affiliationsNoun(),
                        'data' => [],
                        'backgroundColor' => [],
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                    ],
                ],
            ];
        }

        [$r, $g, $b] = $this->accentRgb();
        $max = max(1, max($values));
        $fills = [];
        $hovers = [];

        foreach ($values as $value) {
            $intensity = 0.52 + (0.48 * ($value / $max));
            $fills[] = sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $intensity);
            $hovers[] = sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, min(1, $intensity + 0.14));
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->affiliationsNoun(),
                    'data' => $values,
                    'backgroundColor' => $fills,
                    'hoverBackgroundColor' => $hovers,
                    'borderColor' => array_fill(0, count($values), 'transparent'),
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => $maxBarThickness,
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     kind: string,
     *     year: int,
     *     through_month: int,
     *     labels: list<string>,
     *     values: list<int>,
     *     total: int,
     *     peak_month: int|null,
     *     peak_label: string|null,
     *     peak_total: int
     * }
     */
    private function resolveMonthPayload(): array
    {
        if ($this->resolvedMonthPayload !== null) {
            return $this->resolvedMonthPayload;
        }

        try {
            $this->resolvedMonthPayload = app(AfiliacionesMetricsClient::class)->byMonth($this->affiliationKind());
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico mensual de afiliaciones desde integracorp-api.', [
                'widget' => static::class,
                'kind' => $this->affiliationKind(),
                'message' => $exception->getMessage(),
            ]);

            $now = now();
            $this->resolvedMonthPayload = [
                'kind' => $this->affiliationKind(),
                'year' => (int) $now->year,
                'through_month' => (int) $now->month,
                'labels' => [],
                'values' => [],
                'total' => 0,
                'peak_month' => null,
                'peak_label' => null,
                'peak_total' => 0,
            ];
        }

        return $this->resolvedMonthPayload;
    }

    /**
     * @return array{
     *     kind: string,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     labels: list<string>,
     *     values: list<int>,
     *     total: int,
     *     peak_day: int|null,
     *     peak_label: string|null,
     *     peak_total: int
     * }
     */
    private function resolveDayPayload(): array
    {
        if ($this->resolvedDayPayload !== null) {
            return $this->resolvedDayPayload;
        }

        $month = (int) $this->selectedMonth;
        $year = $this->getChartYear();

        try {
            $this->resolvedDayPayload = app(AfiliacionesMetricsClient::class)->byDay(
                $this->affiliationKind(),
                $year,
                $month,
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico diario de afiliaciones desde integracorp-api.', [
                'widget' => static::class,
                'kind' => $this->affiliationKind(),
                'year' => $year,
                'month' => $month,
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedDayPayload = [
                'kind' => $this->affiliationKind(),
                'year' => $year,
                'month' => $month,
                'month_label' => '',
                'labels' => [],
                'values' => [],
                'total' => 0,
                'peak_day' => null,
                'peak_label' => null,
                'peak_total' => 0,
            ];
        }

        return $this->resolvedDayPayload;
    }
}
