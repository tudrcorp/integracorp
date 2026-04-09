<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Models\Sale;
use App\Support\Charts\TopFiveAgentSalesMonthComparison;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TotalSaleMonthlyNowVsLastAgent extends ChartWidget
{
    use IosLiquidGlassBarChartWidget;

    protected string $view = 'filament.widgets.ios-liquid-glass-bar-chart-widget';

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Top 5 agentes — mes actual vs mes seleccionado';

    public function mount(): void
    {
        parent::mount();

        $filters = $this->getFilters();
        if ($filters !== null && $filters !== [] && ($this->filter === null || ! array_key_exists($this->filter, $filters))) {
            $this->filter = array_key_first($filters);
        }
    }

    /**
     * Meses anteriores al actual dentro del año en curso (clave Y-m, más reciente primero).
     *
     * @return array<string, string>|null
     */
    protected function getFilters(): ?array
    {
        $now = Carbon::now();
        $year = (int) $now->year;
        $currentMonth = (int) $now->month;

        if ($currentMonth <= 1) {
            return null;
        }

        $options = [];
        for ($m = $currentMonth - 1; $m >= 1; $m--) {
            $key = sprintf('%d-%02d', $year, $m);
            $label = Carbon::create($year, $m, 1)->locale(app()->getLocale())->translatedFormat('F Y');
            $options[$key] = ucfirst((string) $label);
        }

        return $options;
    }

    public function getDescription(): ?string
    {
        $now = Carbon::now();
        $currentLabel = ucfirst($now->copy()->locale(app()->getLocale())->translatedFormat('F Y'));

        $filters = $this->getFilters();
        if ($filters === null || $filters === []) {
            return 'En el primer mes del año no hay meses anteriores en el año en curso para comparar. Desde febrero podrás elegir un mes de referencia.';
        }

        $comparisonKey = $this->filter;
        if ($comparisonKey === null || ! array_key_exists($comparisonKey, $filters)) {
            return "Los cinco agentes con mayores ventas (US\$) en {$currentLabel}, comparados con el mes que elijas en el filtro.";
        }

        $comparisonLabel = $filters[$comparisonKey];

        return "Los cinco agentes con mayores ventas (US\$) en {$currentLabel}, comparado con {$comparisonLabel}. Orden por ventas del mes actual.";
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin ventas en el periodo';
    }

    public function getIosBarChartEmptyBody(): string
    {
        $filters = $this->getFilters();
        if ($filters === null || $filters === []) {
            return 'No hay meses anteriores en el año en curso para comparar con el mes actual.';
        }

        return 'No hay ventas registradas en el mes actual o en el mes seleccionado para armar el comparativo.';
    }

    /**
     * @return Collection<int, object{agent_id: int|string, label: string, total: string|float}>
     */
    private function agentTotalsBetween(Carbon $start, Carbon $end): Collection
    {
        return Sale::query()
            ->select([
                'agents.id as agent_id',
                'agents.name as label',
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
            ])
            ->join('agents', 'agents.id', '=', 'sales.agent_id')
            ->whereBetween('sales.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('agents.id', 'agents.name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function emptyChartPayload(string $currentMonthLabel, string $comparisonMonthLabel): array
    {
        $currentFill = 'rgba(16, 185, 129, 0.92)';
        $comparisonFill = 'rgba(14, 165, 233, 0.92)';

        return [
            'datasets' => [
                [
                    'label' => 'Mes actual ('.$currentMonthLabel.')',
                    'data' => [],
                    'backgroundColor' => $currentFill,
                    'borderColor' => 'rgba(5, 150, 105, 1)',
                    'borderWidth' => 1.75,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($currentFill),
                ],
                [
                    'label' => 'Mes comparación ('.$comparisonMonthLabel.')',
                    'data' => [],
                    'backgroundColor' => $comparisonFill,
                    'borderColor' => 'rgba(3, 105, 161, 1)',
                    'borderWidth' => 1.75,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($comparisonFill),
                ],
            ],
            'labels' => [],
        ];
    }

    protected function getData(): array
    {
        $now = Carbon::now();
        $currentMonthLabel = ucfirst($now->copy()->locale(app()->getLocale())->translatedFormat('F Y'));

        $filters = $this->getFilters();
        if ($filters === null || $filters === [] || $this->filter === null || ! array_key_exists($this->filter, $filters)) {
            return $this->emptyChartPayload($currentMonthLabel, '—');
        }

        if (! preg_match('/^(\d{4})-(\d{2})$/', $this->filter, $m)) {
            return $this->emptyChartPayload($currentMonthLabel, '—');
        }

        $compYear = (int) $m[1];
        $compMonth = (int) $m[2];
        $currentYear = (int) $now->year;
        $currentMonth = (int) $now->month;

        if ($compYear !== $currentYear || $compMonth < 1 || $compMonth >= $currentMonth) {
            return $this->emptyChartPayload($currentMonthLabel, $filters[$this->filter]);
        }

        $comparisonLabel = $filters[$this->filter];

        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();
        $comparisonStart = Carbon::create($compYear, $compMonth, 1)->startOfMonth();
        $comparisonEnd = Carbon::create($compYear, $compMonth, 1)->endOfMonth();

        $currentMonthSales = $this->agentTotalsBetween($currentStart, $currentEnd);
        $comparisonMonthSales = $this->agentTotalsBetween($comparisonStart, $comparisonEnd);

        $topFive = TopFiveAgentSalesMonthComparison::mergeAndTakeTopFiveByCurrentMonth(
            $currentMonthSales,
            $comparisonMonthSales,
        );

        $labels = $topFive->pluck('label')->all();
        $currentData = $topFive->pluck('current')->map(fn (float $v) => round($v, 2))->all();
        $comparisonData = $topFive->pluck('previous')->map(fn (float $v) => round($v, 2))->all();

        $currentFill = 'rgba(16, 185, 129, 0.92)';
        $comparisonFill = 'rgba(14, 165, 233, 0.92)';

        return [
            'datasets' => [
                [
                    'label' => 'Mes actual ('.$currentMonthLabel.')',
                    'data' => $currentData,
                    'backgroundColor' => $currentFill,
                    'borderColor' => 'rgba(5, 150, 105, 1)',
                    'borderWidth' => 1.75,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($currentFill),
                ],
                [
                    'label' => 'Mes comparación ('.$comparisonLabel.')',
                    'data' => $comparisonData,
                    'backgroundColor' => $comparisonFill,
                    'borderColor' => 'rgba(3, 105, 161, 1)',
                    'borderWidth' => 1.75,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($comparisonFill),
                ],
            ],
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
        padding: { top: 8, right: 8, bottom: 4, left: 4 }
    },
    interaction: {
        mode: 'index',
        intersect: false
    },
    datasets: {
        bar: {
            categoryPercentage: 0.72,
            barPercentage: 0.78
        }
    },
    elements: {
        bar: {
            borderWidth: 1.75,
            borderRadius: 8,
            borderSkipped: false
        }
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: {
                boxWidth: 12,
                padding: 16,
                font: { size: 11, weight: '600' },
                color: '#8e8e93'
            }
        },
        tooltip: {
            enabled: true,
            backgroundColor: 'rgba(22, 22, 24, 0.56)',
            titleColor: 'rgba(255, 255, 255, 0.92)',
            bodyColor: 'rgba(255, 255, 255, 0.86)',
            borderColor: 'rgba(255, 255, 255, 0.14)',
            borderWidth: 1,
            padding: 10,
            displayColors: true,
            boxPadding: 6,
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return '';
                    const ds = ctx.dataset?.label ? ctx.dataset.label + ': ' : '';
                    return ' ' + ds + 'US$ ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }
        }
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                color: '#8e8e93',
                maxRotation: 45,
                minRotation: 0
            },
            border: { display: false }
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#8e8e93',
                callback: (value) => '$' + Number(value).toLocaleString()
            },
            grid: {
                color: 'rgba(142, 142, 147, 0.18)',
                drawBorder: false
            },
            border: { display: false }
        }
    },
    animation: {
        duration: 900,
        easing: 'easeOutQuart'
    }
}
JS);
    }

    protected function brighterGlassFill(string $rgba): string
    {
        if (preg_match('/rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([0-9.]+)\s*\)/', $rgba, $m) !== 1) {
            return $rgba;
        }

        $r = (int) $m[1];
        $g = (int) $m[2];
        $b = (int) $m[3];
        $a = (float) $m[4];

        $mixTowardsWhite = static function (int $channel): int {
            return (int) round($channel + (255 - $channel) * 0.32);
        };

        $nr = $mixTowardsWhite($r);
        $ng = $mixTowardsWhite($g);
        $nb = $mixTowardsWhite($b);
        $na = min(0.98, $a + 0.06);

        return "rgba({$nr}, {$ng}, {$nb}, {$na})";
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
