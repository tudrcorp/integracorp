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

class CorretajeAgenciesByActiveAffiliationAmountChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '28rem';

    protected string $view = 'filament.metrics.widgets.corretaje-agencies-by-active-affiliation-amount-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Monto US$ de afiliaciones activas por agencia';

    protected ?string $description = 'Individuales y corporativas ACTIVA directas · top 20. Dos barras si la agencia tiene ambos tipos.';

    /**
     * @var array{
     *     items: list<array{agency_code: string, agency_name: string, amount_individual: float, amount_corporate: float, total_amount: float, individual_count: int, corporate_count: int}>,
     *     total_amount: float,
     *     total_individual_amount: float,
     *     total_corporate_amount: float,
     *     total_agencies: int,
     *     limit: int
     * }|null
     */
    private ?array $resolvedPayload = null;

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin montos de afiliaciones por agencia';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay afiliaciones ACTIVA directas (individuales o corporativas) con total_amount para graficar.';
    }

    public function getChartTotalAmount(): float
    {
        return (float) ($this->resolvePayload()['total_amount'] ?? 0);
    }

    public function getChartTotalAmountFormatted(): string
    {
        return number_format($this->getChartTotalAmount(), 2, ',', '.');
    }

    public function getChartIndividualAmount(): float
    {
        return (float) ($this->resolvePayload()['total_individual_amount'] ?? 0);
    }

    public function getChartIndividualAmountFormatted(): string
    {
        return number_format($this->getChartIndividualAmount(), 2, ',', '.');
    }

    public function getChartCorporateAmount(): float
    {
        return (float) ($this->resolvePayload()['total_corporate_amount'] ?? 0);
    }

    public function getChartCorporateAmountFormatted(): string
    {
        return number_format($this->getChartCorporateAmount(), 2, ',', '.');
    }

    public function getChartAgenciesCount(): int
    {
        return (int) ($this->resolvePayload()['total_agencies'] ?? 0);
    }

    public function getChartTopAgencyLabel(): string
    {
        $top = $this->resolveItems()[0] ?? null;

        if ($top === null) {
            return '—';
        }

        return $this->formatAgencyLabel($top['agency_name'], $top['agency_code'], short: true);
    }

    public function getChartTopAgencyAmount(): float
    {
        return (float) (($this->resolveItems()[0]['total_amount'] ?? 0));
    }

    public function getChartTopAgencyAmountFormatted(): string
    {
        return number_format($this->getChartTopAgencyAmount(), 2, ',', '.');
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
                $this->getChartIndividualAmount(),
                $this->getChartCorporateAmount(),
                $this->getChartAgenciesCount(),
                $this->getChartTopAgencyAmount(),
                count($this->resolveItems()),
            ], JSON_THROW_ON_ERROR),
        );

        return 'metrics-agencies-by-active-affiliation-amount-'.$fingerprint;
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
                        'label' => 'Individuales',
                        'data' => [],
                        'backgroundColor' => 'rgba(20, 184, 166, 0.88)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                        'skipNull' => true,
                        'valueLabelFormat' => 'usd',
                    ],
                    [
                        'label' => 'Corporativas',
                        'data' => [],
                        'backgroundColor' => 'rgba(124, 58, 237, 0.88)',
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
                        'borderSkipped' => false,
                        'skipNull' => true,
                        'valueLabelFormat' => 'usd',
                    ],
                ],
            ];
        }

        $labels = [];
        $fullNames = [];
        $individualValues = [];
        $corporateValues = [];
        $individualCounts = [];
        $corporateCounts = [];

        foreach ($items as $item) {
            $labels[] = $this->formatAgencyLabel($item['agency_name'], $item['agency_code'], short: true);
            $fullNames[] = $this->formatAgencyLabel($item['agency_name'], $item['agency_code'], short: false);
            $individualValues[] = $item['amount_individual'] > 0 ? $item['amount_individual'] : null;
            $corporateValues[] = $item['amount_corporate'] > 0 ? $item['amount_corporate'] : null;
            $individualCounts[] = $item['individual_count'];
            $corporateCounts[] = $item['corporate_count'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Individuales',
                    'data' => $individualValues,
                    'fullNames' => $fullNames,
                    'affiliationsCount' => $individualCounts,
                    'backgroundColor' => 'rgba(20, 184, 166, 0.88)',
                    'hoverBackgroundColor' => 'rgba(20, 184, 166, 1)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 44,
                    'skipNull' => true,
                    'valueLabelFormat' => 'usd',
                ],
                [
                    'label' => 'Corporativas',
                    'data' => $corporateValues,
                    'fullNames' => $fullNames,
                    'affiliationsCount' => $corporateCounts,
                    'backgroundColor' => 'rgba(124, 58, 237, 0.88)',
                    'hoverBackgroundColor' => 'rgba(124, 58, 237, 1)',
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 44,
                    'skipNull' => true,
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
            categoryPercentage: 0.78,
            barPercentage: 0.9
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
            borderColor: 'rgba(45, 212, 191, 0.35)',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            boxPadding: 6,
            cornerRadius: 10,
            titleFont: { weight: '600', size: 12 },
            bodyFont: { size: 12 },
            filter: (item) => item.parsed?.y !== null && item.parsed?.y !== undefined,
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
                    const kind = ctx.dataset?.label || 'Afiliaciones';
                    const count = ctx.dataset?.affiliationsCount?.[ctx.dataIndex];
                    const amount = Number(v).toLocaleString('es-VE', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    const countLabel = Number.isFinite(count)
                        ? ' · ' + Number(count).toLocaleString('es-VE') + ' afil.'
                        : '';
                    return ' ' + kind + ': US$ ' + amount + countLabel;
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
     * @return list<array{agency_code: string, agency_name: string, amount_individual: float, amount_corporate: float, total_amount: float, individual_count: int, corporate_count: int}>
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
     *     items: list<array{agency_code: string, agency_name: string, amount_individual: float, amount_corporate: float, total_amount: float, individual_count: int, corporate_count: int}>,
     *     total_amount: float,
     *     total_individual_amount: float,
     *     total_corporate_amount: float,
     *     total_agencies: int,
     *     limit: int
     * }
     */
    private function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(CorretajeAgenciesMetricsClient::class)->byActiveAffiliationAmount(20);
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de montos por agencia desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'items' => [],
                'total_amount' => 0.0,
                'total_individual_amount' => 0.0,
                'total_corporate_amount' => 0.0,
                'total_agencies' => 0,
                'limit' => 20,
            ];
        }

        return $this->resolvedPayload;
    }

    private function formatAgencyLabel(string $name, string $code, bool $short): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($trimmed === '') {
            $trimmed = trim($code) !== '' ? trim($code) : 'Sin nombre';
        }

        $label = $code !== '' && $trimmed !== $code
            ? "{$code} · {$trimmed}"
            : $trimmed;

        if (! $short) {
            return $label;
        }

        return (string) Str::limit($label, 24, '…');
    }
}
