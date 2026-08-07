<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Concerns;

use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

abstract class CorretajeAgenciesAffiliationsByTypeChart extends ChartWidget
{
    use HasMetricsChartPerformance;
    use IosLiquidGlassBarChartWidget;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected ?string $placeholderHeight = '24rem';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    public ?string $drillAgencyType = null;

    /**
     * @var array{
     *     items: list<array{agency_type_id: int, agency_type: string, total: int}>,
     *     total_masters: int,
     *     total_generals: int,
     *     total_affiliations: int
     * }|null
     */
    private ?array $resolvedOverviewPayload = null;

    /**
     * @var array{
     *     agency_type_id: int,
     *     agency_type: string,
     *     items: list<array{agency_code: string, agency_name: string, total: int}>,
     *     total_affiliations: int,
     *     agencies_count: int,
     *     limit: int
     * }|null
     */
    private ?array $resolvedDetailPayload = null;

    abstract protected function resolveAffiliationsPayload(): array;

    /**
     * @return array{
     *     agency_type_id: int,
     *     agency_type: string,
     *     items: list<array{agency_code: string, agency_name: string, total: int}>,
     *     total_affiliations: int,
     *     agencies_count: int,
     *     limit: int
     * }
     */
    abstract protected function resolveAffiliationsDetailPayload(int $agencyTypeId): array;

    abstract protected function chartWireKeyPrefix(): string;

    abstract protected function affiliationsLabel(): string;

    abstract protected function overviewHeading(): string;

    abstract protected function overviewDescription(): string;

    protected function metricsChartPlaceholderHeading(): string
    {
        return $this->overviewHeading();
    }

    public function isDrillDown(): bool
    {
        return in_array($this->drillAgencyType, ['MASTER', 'GENERAL'], true);
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->isDrillDown()) {
            return $this->overviewHeading();
        }

        return 'Detalle · agencias '.$this->drillAgencyType;
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->isDrillDown()) {
            return $this->overviewDescription().' · Clic en una barra para detallar.';
        }

        return 'Top agencias '.$this->drillAgencyType.' · '.$this->affiliationsLabel().' · usa Volver para regresar.';
    }

    /**
     * @param  array{label?: string, index?: int}  $payload
     */
    public function handleChartClick(array $payload = []): void
    {
        if ($this->isDrillDown()) {
            return;
        }

        $label = strtoupper(trim((string) ($payload['label'] ?? '')));
        $index = (int) ($payload['index'] ?? -1);

        if (! in_array($label, ['MASTER', 'GENERAL'], true)) {
            $label = match ($index) {
                0 => 'MASTER',
                1 => 'GENERAL',
                default => '',
            };
        }

        if ($label === '') {
            return;
        }

        $this->drillAgencyType = $label;
        $this->resolvedDetailPayload = null;
        $this->cachedData = null;
        $this->dataChecksum = $this->generateDataChecksum();
    }

    public function resetDrillDown(): void
    {
        $this->drillAgencyType = null;
        $this->resolvedDetailPayload = null;
        $this->cachedData = null;
        $this->dataChecksum = $this->generateDataChecksum();
    }

    public function getChartTotalAffiliations(): int
    {
        if ($this->isDrillDown()) {
            return (int) ($this->resolveDetailPayload()['total_affiliations'] ?? 0);
        }

        return (int) ($this->resolveOverviewPayload()['total_affiliations'] ?? 0);
    }

    public function getChartTotalMasters(): int
    {
        return (int) ($this->resolveOverviewPayload()['total_masters'] ?? 0);
    }

    public function getChartTotalGenerals(): int
    {
        return (int) ($this->resolveOverviewPayload()['total_generals'] ?? 0);
    }

    public function getChartAgenciesCount(): int
    {
        return (int) ($this->resolveDetailPayload()['agencies_count'] ?? 0);
    }

    public function getChartTopAgencyLabel(): string
    {
        $top = $this->resolveDetailPayload()['items'][0] ?? null;

        if ($top === null) {
            return '—';
        }

        return $this->formatAgencyLabel($top['agency_name'], short: true);
    }

    public function getChartTopAgencyTotal(): int
    {
        return (int) (($this->resolveDetailPayload()['items'][0]['total'] ?? 0));
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
                $this->drillAgencyType,
                $this->getChartTotalAffiliations(),
                $this->getChartTotalMasters(),
                $this->getChartTotalGenerals(),
                $this->isDrillDown() ? $this->getChartAgenciesCount() : 0,
                $this->isDrillDown() ? $this->getChartTopAgencyTotal() : 0,
            ], JSON_THROW_ON_ERROR),
        );

        return $this->chartWireKeyPrefix().'-'.$fingerprint;
    }

    public function getIosBarChartEmptyTitle(): string
    {
        if ($this->isDrillDown()) {
            return 'Sin agencias en '.$this->drillAgencyType;
        }

        return 'Sin afiliaciones para graficar';
    }

    public function getIosBarChartEmptyBody(): string
    {
        if ($this->isDrillDown()) {
            return 'No hay '.$this->affiliationsLabel().' directas para agencias '.$this->drillAgencyType.'.';
        }

        return 'No hay '.$this->affiliationsLabel().' directas de agencias MASTER o GENERAL.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return $this->isDrillDown()
            ? $this->buildDetailChartData()
            : $this->buildOverviewChartData();
    }

    protected function getOptions(): RawJs
    {
        $unitLabel = $this->affiliationsLabel();
        $isDetail = $this->isDrillDown() ? 'true' : 'false';
        $tooltipFooter = $this->isDrillDown()
            ? 'Usa el botón Volver para regresar'
            : 'Clic para ver detalle por agencia';

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
            categoryPercentage: {$isDetail} ? 0.86 : 0.72,
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
                font: { size: {$isDetail} ? 9 : 12, weight: {$isDetail} ? '600' : '700' },
                maxRotation: {$isDetail} ? 48 : 0,
                minRotation: {$isDetail} ? 36 : 0,
                autoSkip: false,
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
     * @return array<string, mixed>
     */
    private function buildOverviewChartData(): array
    {
        $payload = $this->resolveOverviewPayload();
        $masters = (int) $payload['total_masters'];
        $generals = (int) $payload['total_generals'];
        $borderRadius = [
            'topLeft' => 10,
            'topRight' => 10,
            'bottomLeft' => 0,
            'bottomRight' => 0,
        ];

        return [
            'labels' => ['MASTER', 'GENERAL'],
            'datasets' => [
                [
                    'label' => $this->affiliationsLabel(),
                    'data' => [$masters, $generals],
                    'backgroundColor' => [
                        'rgba(14, 165, 233, 0.85)',
                        'rgba(124, 58, 237, 0.85)',
                    ],
                    'hoverBackgroundColor' => [
                        'rgba(14, 165, 233, 0.98)',
                        'rgba(124, 58, 237, 0.98)',
                    ],
                    'borderColor' => ['transparent', 'transparent'],
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 96,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDetailChartData(): array
    {
        $items = array_values(array_filter(
            $this->resolveDetailPayload()['items'],
            static fn (array $item): bool => $item['total'] > 0,
        ));

        $accent = $this->drillAgencyType === 'GENERAL'
            ? [124, 58, 237]
            : [14, 165, 233];

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
                        'label' => $this->affiliationsLabel(),
                        'data' => [],
                        'backgroundColor' => [],
                        'borderWidth' => 0,
                        'borderRadius' => $borderRadius,
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

        foreach ($items as $item) {
            $labels[] = $this->formatAgencyLabel($item['agency_name'], short: true);
            $values[] = $item['total'];
            $intensity = 0.55 + (0.45 * ($item['total'] / $max));
            $fills[] = sprintf('rgba(%d, %d, %d, %.2f)', $accent[0], $accent[1], $accent[2], $intensity);
            $hovers[] = sprintf('rgba(%d, %d, %d, %.2f)', $accent[0], $accent[1], $accent[2], min(1, $intensity + 0.12));
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $this->affiliationsLabel(),
                    'data' => $values,
                    'backgroundColor' => $fills,
                    'hoverBackgroundColor' => $hovers,
                    'borderColor' => array_fill(0, count($values), 'transparent'),
                    'borderWidth' => 0,
                    'borderRadius' => $borderRadius,
                    'borderSkipped' => false,
                    'maxBarThickness' => 52,
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     items: list<array{agency_type_id: int, agency_type: string, total: int}>,
     *     total_masters: int,
     *     total_generals: int,
     *     total_affiliations: int
     * }
     */
    private function resolveOverviewPayload(): array
    {
        if ($this->resolvedOverviewPayload !== null) {
            return $this->resolvedOverviewPayload;
        }

        try {
            $this->resolvedOverviewPayload = $this->resolveAffiliationsPayload();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el gráfico de afiliaciones por tipo de agencia desde integracorp-api.', [
                'widget' => static::class,
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedOverviewPayload = [
                'items' => [],
                'total_masters' => 0,
                'total_generals' => 0,
                'total_affiliations' => 0,
            ];
        }

        return $this->resolvedOverviewPayload;
    }

    /**
     * @return array{
     *     agency_type_id: int,
     *     agency_type: string,
     *     items: list<array{agency_code: string, agency_name: string, total: int}>,
     *     total_affiliations: int,
     *     agencies_count: int,
     *     limit: int
     * }
     */
    private function resolveDetailPayload(): array
    {
        if ($this->resolvedDetailPayload !== null) {
            return $this->resolvedDetailPayload;
        }

        $agencyTypeId = $this->drillAgencyType === 'GENERAL' ? 3 : 1;

        try {
            $this->resolvedDetailPayload = $this->resolveAffiliationsDetailPayload($agencyTypeId);
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el detalle de afiliaciones por agencia desde integracorp-api.', [
                'widget' => static::class,
                'agency_type' => $this->drillAgencyType,
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedDetailPayload = [
                'agency_type_id' => $agencyTypeId,
                'agency_type' => (string) $this->drillAgencyType,
                'items' => [],
                'total_affiliations' => 0,
                'agencies_count' => 0,
                'limit' => 0,
            ];
        }

        return $this->resolvedDetailPayload;
    }

    private function formatAgencyLabel(string $name, bool $short = false): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return 'Sin nombre';
        }

        $normalized = Str::title(mb_strtolower($trimmed));

        if (! $short || mb_strlen($normalized) <= 18) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, 16)).'…';
    }
}
