<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\FormatsCotizacionesMomChip;
use App\Filament\Metrics\Widgets\Concerns\HasMetricsChartPerformance;
use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Services\IntegracorpApi\CotizacionesMetricsClient;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CotizacionesByAgencyChart extends ChartWidget
{
    use FormatsCotizacionesMomChip;
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '28rem';

    protected string $view = 'filament.metrics.widgets.cotizaciones-by-agency-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Cotizaciones por agencia';

    protected ?string $description = 'Comparamos el mes actual con el mes pasado. Las barras muestran cuántas cotizaciones tiene cada agencia (MASTER o GENERAL) y cuántas ya se convirtieron en afiliación. Solo se muestran las 25 con más actividad.';

    /**
     * @var array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     items: list<array{agency_id: int, agency_code: string|null, agency_name: string, agency_type: string, agency_type_id: int, quotes_total: int, executed_with_affiliation: int, remaining: int, quotes_total_previous: int, executed_with_affiliation_previous: int, quotes_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}>,
     *     total_quotes: int,
     *     total_executed_with_affiliation: int,
     *     total_agencies: int,
     *     conversion_rate: float,
     *     mom: array{quotes: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}},
     *     limit: int
     * }|null
     */
    private ?array $resolvedPayload = null;

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin cotizaciones por agencia';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay cotizaciones individuales o corporativas asociadas a agencias MASTER o GENERAL para graficar.';
    }

    public function getChartTotalQuotes(): int
    {
        return (int) ($this->resolvePayload()['total_quotes'] ?? 0);
    }

    public function getChartExecutedWithAffiliation(): int
    {
        return (int) ($this->resolvePayload()['total_executed_with_affiliation'] ?? 0);
    }

    public function getChartAgenciesCount(): int
    {
        return (int) ($this->resolvePayload()['total_agencies'] ?? 0);
    }

    public function getChartConversionRateLabel(): string
    {
        return number_format((float) ($this->resolvePayload()['conversion_rate'] ?? 0), 1, ',', '.').'%';
    }

    public function getChartCurrentMonthLabel(): string
    {
        $month = $this->resolvePayload()['current_month'] ?? [];

        return $this->formatCotizacionesMonthLabel(
            (int) ($month['year'] ?? 0),
            (int) ($month['month'] ?? 0),
        );
    }

    public function getChartPreviousMonthLabel(): string
    {
        $month = $this->resolvePayload()['previous_month'] ?? [];

        return $this->formatCotizacionesMonthLabel(
            (int) ($month['year'] ?? 0),
            (int) ($month['month'] ?? 0),
        );
    }

    public function getChartQuotesMomLabel(): string
    {
        return $this->formatCotizacionesMomPercentLabel($this->resolvePayload()['mom']['quotes'] ?? []);
    }

    public function getChartQuotesMomTrend(): string
    {
        return $this->formatCotizacionesMomTrend($this->resolvePayload()['mom']['quotes'] ?? []);
    }

    public function getChartExecutedMomLabel(): string
    {
        return $this->formatCotizacionesMomPercentLabel($this->resolvePayload()['mom']['executed'] ?? []);
    }

    public function getChartExecutedMomTrend(): string
    {
        return $this->formatCotizacionesMomTrend($this->resolvePayload()['mom']['executed'] ?? []);
    }

    /**
     * @return array{
     *     current_label: string,
     *     previous_label: string,
     *     intro: string,
     *     entity_label: string,
     *     entity_count: int,
     *     entity_help: string,
     *     conversion_label: string,
     *     conversion_help: string,
     *     top_label: string,
     *     top_total: int,
     *     top_help: string,
     *     quotes: array{current: int, previous: int, percent_label: string, delta_label: string, delta_sentence: string, trend: string},
     *     executed: array{current: int, previous: int, percent_label: string, delta_label: string, delta_sentence: string, trend: string}
     * }
     */
    public function getMomSummaryViewData(): array
    {
        $quotesMom = $this->resolvePayload()['mom']['quotes'] ?? [];
        $executedMom = $this->resolvePayload()['mom']['executed'] ?? [];

        return [
            'current_label' => $this->getChartCurrentMonthLabel(),
            'previous_label' => $this->getChartPreviousMonthLabel(),
            'intro' => 'Este resumen compara lo que va del mes actual con todo el mes pasado, agencia por agencia. Sirve para ver si la actividad está subiendo o bajando.',
            'entity_label' => 'Agencias con actividad',
            'entity_count' => $this->getChartAgenciesCount(),
            'entity_help' => 'Cantidad de agencias MASTER o GENERAL con al menos una cotización este mes.',
            'conversion_label' => $this->getChartConversionRateLabel(),
            'conversion_help' => 'De cada 100 cotizaciones de este mes, cuántas ya quedaron convertidas en afiliación.',
            'top_label' => $this->getChartTopAgencyLabel(),
            'top_total' => $this->getChartTopAgencyTotal(),
            'top_help' => 'Es la agencia con más cotizaciones en el mes actual.',
            'quotes' => [
                'current' => $this->getChartTotalQuotes(),
                'previous' => (int) ($quotesMom['previous'] ?? 0),
                'percent_label' => $this->formatCotizacionesMomPercentLabel($quotesMom),
                'delta_label' => $this->formatCotizacionesMomDeltaLabel($quotesMom),
                'delta_sentence' => $this->formatCotizacionesMomDeltaSentence($quotesMom, 'cotizaciones'),
                'trend' => $this->formatCotizacionesMomTrend($quotesMom),
            ],
            'executed' => [
                'current' => $this->getChartExecutedWithAffiliation(),
                'previous' => (int) ($executedMom['previous'] ?? 0),
                'percent_label' => $this->formatCotizacionesMomPercentLabel($executedMom),
                'delta_label' => $this->formatCotizacionesMomDeltaLabel($executedMom),
                'delta_sentence' => $this->formatCotizacionesMomDeltaSentence($executedMom, 'afiliaciones'),
                'trend' => $this->formatCotizacionesMomTrend($executedMom),
            ],
        ];
    }

    public function getChartTopAgencyLabel(): string
    {
        $top = $this->resolveItems()[0] ?? null;

        if ($top === null) {
            return '—';
        }

        return $this->formatAgencyLabel(
            $top['agency_name'],
            $top['agency_code'],
            $top['agency_type'],
            short: true,
        );
    }

    public function getChartTopAgencyTotal(): int
    {
        return (int) (($this->resolveItems()[0]['quotes_total'] ?? 0));
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
                $this->getChartTotalQuotes(),
                $this->getChartExecutedWithAffiliation(),
                $this->getChartAgenciesCount(),
                $this->getChartTopAgencyTotal(),
                count($this->resolveItems()),
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-cotizaciones-by-agency-'.$fingerprint;
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
                        'label' => 'Total de cotizaciones',
                        'data' => [],
                        'backgroundColor' => 'rgba(14, 165, 233, 0.88)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                    ],
                    [
                        'label' => 'Convertidas en afiliación',
                        'data' => [],
                        'backgroundColor' => 'rgba(16, 185, 129, 0.90)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                    ],
                ],
            ];
        }

        $labels = [];
        $fullNames = [];
        $totalValues = [];
        $executedValues = [];
        $totalMomLabels = [];
        $executedMomLabels = [];
        $previousMonthLabel = $this->getChartPreviousMonthLabel();

        foreach ($items as $item) {
            $labels[] = $this->formatAgencyLabel(
                $item['agency_name'],
                $item['agency_code'],
                $item['agency_type'],
                short: true,
            );
            $fullNames[] = $this->formatAgencyLabel(
                $item['agency_name'],
                $item['agency_code'],
                $item['agency_type'],
                short: false,
            );
            $totalValues[] = $item['quotes_total'];
            $executedValues[] = $item['executed_with_affiliation'];
            $totalMomLabels[] = $this->formatCotizacionesMomPercentLabel($item['quotes_mom']);
            $executedMomLabels[] = $this->formatCotizacionesMomPercentLabel($item['executed_mom']);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total de cotizaciones',
                    'data' => $totalValues,
                    'fullNames' => $fullNames,
                    'momLabels' => $totalMomLabels,
                    'previousMonthLabel' => $previousMonthLabel,
                    'backgroundColor' => 'rgba(14, 165, 233, 0.88)',
                    'hoverBackgroundColor' => 'rgba(14, 165, 233, 1)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 28,
                ],
                [
                    'label' => 'Convertidas en afiliación',
                    'data' => $executedValues,
                    'fullNames' => $fullNames,
                    'momLabels' => $executedMomLabels,
                    'previousMonthLabel' => $previousMonthLabel,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.90)',
                    'hoverBackgroundColor' => 'rgba(16, 185, 129, 1)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 28,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        $yAxisMax = $this->resolveYAxisMax();

        return RawJs::make(<<<JS
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
            borderColor: 'rgba(14, 165, 233, 0.35)',
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
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return null;
                    const kind = ctx.dataset?.label || 'Cotizaciones';
                    const mom = ctx.dataset?.momLabels?.[ctx.dataIndex];
                    const base = ' ' + kind + ': ' + Number(v).toLocaleString('es-VE');
                    if (!mom) return base;
                    return base + ' · frente al mes pasado: ' + mom;
                }
            }
        }
    },
    scales: {
        x: {
            stacked: false,
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
            stacked: false,
            beginAtZero: true,
            max: {$yAxisMax},
            grace: '0%',
            grid: {
                color: 'rgba(100, 116, 139, 0.14)',
                drawBorder: false
            },
            border: { display: false },
            ticks: {
                color: '#64748b',
                precision: 0,
                font: { size: 11, weight: '500' },
                maxTicksLimit: 5
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
     * Techo del eje Y con margen superior para que la barra más alta no se corte.
     */
    private function resolveYAxisMax(): int
    {
        $highest = 0;

        foreach ($this->resolveItems() as $item) {
            $highest = max($highest, (int) $item['quotes_total']);
        }

        if ($highest <= 0) {
            return 10;
        }

        return $this->niceAxisMax((int) ceil($highest * 1.18));
    }

    private function niceAxisMax(int $value): int
    {
        $value = max(1, $value);

        if ($value <= 10) {
            return 10;
        }

        if ($value <= 20) {
            return 20;
        }

        if ($value <= 50) {
            return 50;
        }

        $step = match (true) {
            $value <= 100 => 10,
            $value <= 200 => 20,
            $value <= 500 => 25,
            default => 50,
        };

        return (int) (ceil($value / $step) * $step);
    }

    /**
     * @return list<array{agency_id: int, agency_code: string|null, agency_name: string, agency_type: string, agency_type_id: int, quotes_total: int, executed_with_affiliation: int, remaining: int, quotes_total_previous: int, executed_with_affiliation_previous: int, quotes_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}>
     */
    private function resolveItems(): array
    {
        return array_values(array_filter(
            $this->resolvePayload()['items'],
            static fn (array $item): bool => $item['quotes_total'] > 0,
        ));
    }

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     items: list<array{agency_id: int, agency_code: string|null, agency_name: string, agency_type: string, agency_type_id: int, quotes_total: int, executed_with_affiliation: int, remaining: int, quotes_total_previous: int, executed_with_affiliation_previous: int, quotes_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}>,
     *     total_quotes: int,
     *     total_executed_with_affiliation: int,
     *     total_agencies: int,
     *     conversion_rate: float,
     *     mom: array{quotes: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}},
     *     limit: int
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(CotizacionesMetricsClient::class)->byAgency(25);
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de cotizaciones por agencia desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $emptyMom = [
                'current' => 0,
                'previous' => 0,
                'delta' => 0,
                'percent_change' => 0.0,
                'trend' => 'flat',
                'previous_was_zero' => true,
            ];
            $now = now();
            $previous = $now->copy()->subMonthNoOverflow()->startOfMonth();

            $this->resolvedPayload = [
                'current_month' => [
                    'year' => (int) $now->year,
                    'month' => (int) $now->month,
                    'start' => $now->copy()->startOfMonth()->toDateString(),
                    'end_exclusive' => $now->copy()->startOfMonth()->addMonth()->toDateString(),
                ],
                'previous_month' => [
                    'year' => (int) $previous->year,
                    'month' => (int) $previous->month,
                    'start' => $previous->toDateString(),
                    'end_exclusive' => $now->copy()->startOfMonth()->toDateString(),
                ],
                'items' => [],
                'total_quotes' => 0,
                'total_executed_with_affiliation' => 0,
                'total_agencies' => 0,
                'conversion_rate' => 0.0,
                'mom' => [
                    'quotes' => $emptyMom,
                    'executed' => $emptyMom,
                ],
                'limit' => 25,
            ];
        }

        return $this->resolvedPayload;
    }

    private function formatAgencyLabel(string $name, ?string $agencyCode, string $agencyType, bool $short): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($trimmed === '') {
            $trimmed = 'Sin nombre';
        }

        $typePrefix = match ($agencyType) {
            'MASTER' => 'MASTER',
            'GENERAL' => 'GENERAL',
            default => null,
        };

        $parts = array_values(array_filter([
            $typePrefix,
            $agencyCode !== null && $agencyCode !== '' ? $agencyCode : null,
            $trimmed,
        ]));

        $label = implode(' · ', $parts);

        if (! $short) {
            return $label;
        }

        return (string) Str::limit($label, 24, '…');
    }
}
