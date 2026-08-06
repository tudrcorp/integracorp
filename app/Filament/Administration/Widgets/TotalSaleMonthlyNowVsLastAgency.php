<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets;

use App\Models\Sale;
use App\Support\Charts\TopAgencySalesMonthComparison;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TotalSaleMonthlyNowVsLastAgency extends ChartWidget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.administration.widgets.agency-sales-mom-line-chart';

    protected ?string $heading = 'Top 10 agencias — ventas';

    protected ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 'full';

    protected string $color = 'info';

    /**
     * Claves Y-n de meses de comparación activos (además del mes en curso).
     *
     * @var list<string>
     */
    public array $comparisonMonths = [];

    public bool $monthlyChartExpanded = false;

    /**
     * @var list<array{border: string, background: string}>
     */
    private const COMPARISON_PALETTE = [
        ['border' => 'rgba(245, 158, 11, 1)', 'background' => 'rgba(245, 158, 11, 0.72)'],
        ['border' => 'rgba(16, 185, 129, 1)', 'background' => 'rgba(16, 185, 129, 0.72)'],
        ['border' => 'rgba(168, 85, 247, 1)', 'background' => 'rgba(168, 85, 247, 0.72)'],
        ['border' => 'rgba(236, 72, 153, 1)', 'background' => 'rgba(236, 72, 153, 0.72)'],
        ['border' => 'rgba(34, 211, 238, 1)', 'background' => 'rgba(34, 211, 238, 0.72)'],
        ['border' => 'rgba(248, 113, 113, 1)', 'background' => 'rgba(248, 113, 113, 0.72)'],
        ['border' => 'rgba(163, 230, 53, 1)', 'background' => 'rgba(163, 230, 53, 0.72)'],
        ['border' => 'rgba(129, 140, 248, 1)', 'background' => 'rgba(129, 140, 248, 0.72)'],
        ['border' => 'rgba(251, 146, 60, 1)', 'background' => 'rgba(251, 146, 60, 0.72)'],
        ['border' => 'rgba(125, 211, 252, 1)', 'background' => 'rgba(125, 211, 252, 0.72)'],
        ['border' => 'rgba(244, 114, 182, 1)', 'background' => 'rgba(244, 114, 182, 0.72)'],
        ['border' => 'rgba(52, 211, 153, 1)', 'background' => 'rgba(52, 211, 153, 0.72)'],
    ];

    public function mount(): void
    {
        $previous = now()->subMonthNoOverflow();
        $this->comparisonMonths = [$previous->format('Y-n')];

        parent::mount();
    }

    public function getDescription(): ?string
    {
        return 'Comparación mensual interactiva y ranking acumulado del año en curso (US$ total_amount).';
    }

    public function getMonthlyChartHeading(): string
    {
        return 'Comparación mensual';
    }

    public function getMonthlyChartDescription(): string
    {
        $now = Carbon::now();
        $currentLabel = ucfirst($now->copy()->locale(app()->getLocale())->translatedFormat('F Y'));
        $selectedCount = count($this->comparisonMonths);

        if ($selectedCount === 0) {
            return "Top 10 por ventas del mes en curso ({$currentLabel}). Activa meses abajo para comparar barras adicionales.";
        }

        return "Top 10 agencias por ventas US\$ en {$currentLabel}. Comparando con {$selectedCount} mes(es) seleccionado(s).";
    }

    public function getYearToDateChartHeading(): string
    {
        $year = Carbon::now()->year;

        return "Top 10 del año {$year}";
    }

    public function getYearToDateChartDescription(): string
    {
        $now = Carbon::now();
        $year = $now->year;
        $from = $now->copy()->startOfYear()->locale(app()->getLocale())->translatedFormat('d M');
        $to = $now->copy()->locale(app()->getLocale())->translatedFormat('d M Y');

        return "Agencias con mayor venta acumulada en {$year} (desde {$from} hasta {$to}).";
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    public function getYearToDateChartData(): array
    {
        $now = Carbon::now();
        $yearLabel = (string) $now->year;

        $yearTotals = $this->agencyTotalsBetween(
            $now->copy()->startOfYear(),
            $now->copy()->endOfDay(),
        );

        $top = TopAgencySalesMonthComparison::takeTopByTotal($yearTotals, 10);

        return [
            'datasets' => [
                [
                    'label' => 'Año '.$yearLabel,
                    'data' => $top->pluck('total')->map(fn (float $v): float => round($v, 2))->all(),
                    'borderColor' => 'rgba(79, 70, 229, 1)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.78)',
                    'hoverBackgroundColor' => 'rgba(79, 70, 229, 0.95)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'maxBarThickness' => 36,
                ],
            ],
            'labels' => $top->pluck('label')->all(),
        ];
    }

    /**
     * Meses disponibles: enero del año anterior → mes previo al actual.
     *
     * @return list<array{key: string, label: string, short: string, year: int}>
     */
    public function getAvailableComparisonMonths(): array
    {
        $now = Carbon::now();
        $cursor = $now->copy()->subYear()->startOfYear();
        $end = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $months = [];

        while ($cursor->lte($end)) {
            $months[] = [
                'key' => $cursor->format('Y-n'),
                'label' => ucfirst($cursor->copy()->locale(app()->getLocale())->translatedFormat('M Y')),
                'short' => ucfirst($cursor->copy()->locale(app()->getLocale())->translatedFormat('M')),
                'year' => (int) $cursor->year,
            ];
            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    public function toggleComparisonMonth(string $monthKey): void
    {
        if (! $this->isSelectableMonthKey($monthKey)) {
            return;
        }

        if (in_array($monthKey, $this->comparisonMonths, true)) {
            $this->comparisonMonths = array_values(array_filter(
                $this->comparisonMonths,
                static fn (string $key): bool => $key !== $monthKey,
            ));
        } else {
            $this->comparisonMonths[] = $monthKey;
            $this->comparisonMonths = $this->sortMonthKeys($this->comparisonMonths);
        }

        // Solo refresca el gráfico mensual (remount vía wire:key).
        // No llamar updateChartData(): el evento de Filament lo reciben todos los canvas
        // del widget y corrompería el ranking anual.
        $this->cachedData = null;
        $this->dataChecksum = null;
    }

    /**
     * Evita el broadcast de Filament a todos los canvas del widget.
     * El mensual se remonta con wire:key; el anual queda aislado con wire:ignore.
     */
    public function updateChartData(): void
    {
        //
    }

    public function selectedComparisonMonthsCount(): int
    {
        return count($this->comparisonMonths);
    }

    public function toggleMonthlyChart(): void
    {
        $this->monthlyChartExpanded = ! $this->monthlyChartExpanded;
    }

    public function isComparisonMonthActive(string $monthKey): bool
    {
        return in_array($monthKey, $this->comparisonMonths, true);
    }

    public function chartWireKey(): string
    {
        $months = $this->comparisonMonths;
        sort($months);

        return 'agency-sales-mom-'.md5(implode('|', $months));
    }

    /**
     * @return Collection<int, object{agency_code: string, label: string, total: float|string}>
     */
    private function agencyTotalsBetween(Carbon $start, Carbon $end): Collection
    {
        $rows = Sale::query()
            ->select([
                'sales.code_agency as agency_code',
                DB::raw('COALESCE(agencies.name_corporative, sales.code_agency) as label'),
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
            ])
            ->leftJoin('agencies', 'agencies.code', '=', 'sales.code_agency')
            ->whereNotNull('sales.code_agency')
            ->where('sales.code_agency', '!=', '')
            // Excluye ventas de agente directo bajo TUDRENCASA (casa matriz).
            ->where(function ($query): void {
                $query->whereNull('sales.agent_id')
                    ->orWhere('sales.code_agency', '<>', 'TDG-100')
                    ->orWhereNull('sales.owner_code')
                    ->orWhere('sales.owner_code', '<>', 'TDG-100');
            })
            ->whereBetween('sales.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('sales.code_agency', 'agencies.name_corporative')
            ->orderByDesc('total')
            ->get();

        return $rows->map(function (object $row): object {
            $code = strtoupper(trim((string) ($row->agency_code ?? '')));

            if ($code === 'TDG-100') {
                $row->label = 'TUDRENCASA';
            } elseif (! filled($row->label)) {
                $row->label = $code !== '' ? $code : 'Sin agencia';
            }

            $row->agency_code = $code;

            return $row;
        });
    }

    protected function getData(): array
    {
        $now = Carbon::now();
        $currentLabel = ucfirst($now->copy()->locale(app()->getLocale())->translatedFormat('F Y'));

        $currentTotals = $this->agencyTotalsBetween(
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        );

        $top = TopAgencySalesMonthComparison::mergeAndTakeTopByCurrentMonth(
            $currentTotals,
            collect(),
            10,
        );

        $labels = $top->pluck('label')->all();
        $orderedCodes = $top->pluck('agency_code')->all();

        $datasets = [
            [
                'label' => 'Mes en curso ('.$currentLabel.')',
                'data' => $top->pluck('current')->map(fn (float $v): float => round($v, 2))->all(),
                'borderColor' => 'rgba(14, 165, 233, 1)',
                'backgroundColor' => 'rgba(14, 165, 233, 0.72)',
                'hoverBackgroundColor' => 'rgba(14, 165, 233, 0.9)',
                'borderWidth' => 1,
                'borderRadius' => 6,
                'borderSkipped' => false,
                'maxBarThickness' => 28,
            ],
        ];

        foreach ($this->comparisonMonths as $index => $monthKey) {
            $parsed = $this->parseMonthKey($monthKey);
            if ($parsed === null) {
                continue;
            }

            [$year, $month] = $parsed;
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $monthLabel = ucfirst($start->copy()->locale(app()->getLocale())->translatedFormat('F Y'));

            $totalsByCode = $this->agencyTotalsBetween($start, $end)
                ->keyBy(fn (object $row): string => strtoupper(trim((string) $row->agency_code)));

            $series = [];
            foreach ($orderedCodes as $code) {
                $row = $totalsByCode->get((string) $code);
                $series[] = round($row ? (float) $row->total : 0.0, 2);
            }

            $palette = self::COMPARISON_PALETTE[$index % count(self::COMPARISON_PALETTE)];

            $datasets[] = [
                'label' => $monthLabel,
                'data' => $series,
                'borderColor' => $palette['border'],
                'backgroundColor' => $palette['background'],
                'hoverBackgroundColor' => $palette['border'],
                'borderWidth' => 1,
                'borderRadius' => 6,
                'borderSkipped' => false,
                'maxBarThickness' => 28,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
{
    responsive: true,
    maintainAspectRatio: false,
    layout: {
        padding: { top: 20, right: 20, bottom: 10, left: 6 }
    },
    interaction: {
        mode: 'index',
        intersect: false
    },
    datasets: {
        bar: {
            categoryPercentage: 0.72,
            barPercentage: 0.86
        }
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: {
                boxWidth: 12,
                padding: 16,
                font: { size: 12, weight: '600' },
                color: '#8e8e93'
            }
        },
        tooltip: {
            enabled: true,
            backgroundColor: 'rgba(22, 22, 24, 0.88)',
            titleColor: 'rgba(255, 255, 255, 0.95)',
            bodyColor: 'rgba(255, 255, 255, 0.85)',
            borderColor: 'rgba(255, 255, 255, 0.15)',
            borderWidth: 1,
            padding: 12,
            cornerRadius: 10,
            displayColors: true,
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 11 },
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return '';
                    const ds = ctx.dataset?.label ? ctx.dataset.label + ': ' : '';
                    return ' ' + ds + 'US$ ' + Number(v).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        }
    },
    scales: {
        x: {
            grid: {
                display: true,
                drawOnChartArea: true,
                drawTicks: false,
                color: 'rgba(142, 142, 147, 0.22)',
                lineWidth: 1
            },
            ticks: {
                color: '#8e8e93',
                maxRotation: 40,
                minRotation: 20,
                autoSkip: false,
                font: { size: 11, weight: '500' }
            },
            border: { display: false }
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#8e8e93',
                font: { size: 11 },
                padding: 6,
                callback: (value) => 'US$ ' + Number(value).toLocaleString()
            },
            grid: {
                color: 'rgba(142, 142, 147, 0.16)',
                drawBorder: false,
                drawTicks: false
            },
            border: { display: false }
        }
    },
    animation: {
        duration: 750,
        easing: 'easeOutCubic'
    },
    transitions: {
        active: {
            animation: {
                duration: 400
            }
        }
    }
}
JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function isSelectableMonthKey(string $monthKey): bool
    {
        foreach ($this->getAvailableComparisonMonths() as $month) {
            if ($month['key'] === $monthKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function sortMonthKeys(array $keys): array
    {
        usort($keys, function (string $a, string $b): int {
            $parsedA = $this->parseMonthKey($a);
            $parsedB = $this->parseMonthKey($b);

            if ($parsedA === null || $parsedB === null) {
                return strcmp($a, $b);
            }

            return [$parsedA[0], $parsedA[1]] <=> [$parsedB[0], $parsedB[1]];
        });

        return array_values($keys);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parseMonthKey(string $monthKey): ?array
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $monthKey, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];

        if ($month < 1 || $month > 12) {
            return null;
        }

        return [$year, $month];
    }
}
