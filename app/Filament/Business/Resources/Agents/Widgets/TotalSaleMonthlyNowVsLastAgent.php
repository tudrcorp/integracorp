<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Widgets\Concerns\HasYearMonthChartFilters;
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
    use HasYearMonthChartFilters;
    use IosLiquidGlassBarChartWidget;

    protected string $view = 'filament.widgets.ios-liquid-glass-bar-chart-widget';

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Top 10 agentes — mes actual vs mes seleccionado';

    public function mount(): void
    {
        $this->applyDefaultYearMonthForMountPreviousMonth();
        parent::mount();
    }

    /**
     * Solo meses 1–12 (sin «Todo el año»): la comparación es siempre mes contra mes.
     *
     * @return array<string, string>
     */
    public function getChartMonthOptions(): array
    {
        $year = (int) ($this->filterYear ?? now()->year);
        $now = now();
        $maxMonth = 12;
        if ($year === (int) $now->year) {
            $maxMonth = (int) $now->month;
        }

        $options = [];
        for ($m = 1; $m <= $maxMonth; $m++) {
            $key = (string) $m;
            $options[$key] = ucfirst(Carbon::createFromDate($year, $m, 1)->locale(app()->getLocale())->translatedFormat('F'));
        }

        return $options;
    }

    protected function clampMonthToSelectedYear(): void
    {
        $year = (int) ($this->filterYear ?? now()->year);
        $now = now();
        $max = 12;
        if ($year === (int) $now->year) {
            $max = (int) $now->month;
        }
        $m = (int) ($this->filterMonth ?? $max);
        $this->filterMonth = max(1, min($max, $m));
    }

    public function getDescription(): ?string
    {
        $now = Carbon::now();
        $currentLabel = ucfirst($now->copy()->locale(app()->getLocale())->translatedFormat('F Y'));
        [$cy, $cm] = $this->resolveSelectedYearMonth();
        $cm = (int) ($cm ?? 1);
        $comparisonLabel = ucfirst(Carbon::createFromDate($cy, $cm, 1)->locale(app()->getLocale())->translatedFormat('F Y'));

        return "Los diez agentes con mayores ventas (US\$) en {$currentLabel}, comparados con {$comparisonLabel}. El mes de comparación se elige con los filtros de año y mes. Orden por ventas del mes actual.";
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin ventas en el periodo';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay ventas registradas en el mes actual o en el mes de comparación seleccionado.';
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

    protected function getData(): array
    {
        $now = Carbon::now();
        $currentMonthLabel = ucfirst($now->copy()->locale(app()->getLocale())->translatedFormat('F Y'));

        [$compYear, $compMonth] = $this->resolveSelectedYearMonth();
        $compMonth = (int) ($compMonth ?? 1);
        $comparisonLabel = ucfirst(Carbon::createFromDate($compYear, $compMonth, 1)->locale(app()->getLocale())->translatedFormat('F Y'));

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

        $currentFill = 'rgba(46, 134, 193, 0.92)';
        $comparisonFill = 'rgba(166, 172, 175, 0.92)';

        return [
            'datasets' => [
                [
                    'label' => 'Mes actual ('.$currentMonthLabel.')',
                    'data' => $currentData,
                    'backgroundColor' => $currentFill,
                    'borderColor' => 'rgba(46, 134, 193, 0.92)',
                    'borderWidth' => 1.75,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($currentFill),
                ],
                [
                    'label' => 'Mes comparación ('.$comparisonLabel.')',
                    'data' => $comparisonData,
                    'backgroundColor' => $comparisonFill,
                    'borderColor' => 'rgba(166, 172, 175, 0.92)',
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
        padding: { top: 20, right: 20, bottom: 4, left: 4 }
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
                font: { size: 11, weight: '600', family: 'Inter, sans-serif' },
                color: '#8e8e93'
            }
        },
        tooltip: {
            enabled: true,
            backgroundColor: 'rgba(22, 22, 24, 0.88)',
            titleColor: 'rgba(255, 255, 255, 0.95)',
            bodyColor: 'rgba(255, 255, 255, 0.8)',
            borderColor: 'rgba(255, 255, 255, 0.15)',
            borderWidth: 1,
            padding: 12,
            cornerRadius: 10,
            displayColors: true,
            boxPadding: 6,
            callbacks: {
                // Formateo de las etiquetas de moneda
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return '';
                    const ds = ctx.dataset?.label ? ctx.dataset.label + ': ' : '';
                    return ' ' + ds + 'US$ ' + Number(v).toLocaleString(undefined, { 
                        minimumFractionDigits: 2, 
                        maximumFractionDigits: 2 
                    });
                },
                // INDICADOR VISUAL DE COMPARACIÓN
                // Dentro de plugins -> tooltip -> callbacks -> footer
            footer: (tooltipItems) => {
                    const current = tooltipItems.find(i => i.datasetIndex === 0)?.parsed.y || 0;
                    const previous = tooltipItems.find(i => i.datasetIndex === 1)?.parsed.y || 0;

                    // Caso 1: Usuario Nuevo (Sin ventas el mes anterior pero con ventas ahora)
                    if (previous === 0 && current > 0) {
                        return 'N/A (nuevo registro)';
                    }

                    // Caso 2: Sin cambios (Ventas idénticas en ambos meses)
                    if (current === previous) {
                        return '= Sin variaciones';
                    }

    // Caso 3: Cálculo de incremento o descenso
    const diff = ((current - previous) / previous) * 100;
    const icon = diff > 0 ? '▲' : '▼';
    const trend = diff > 0 ? 'incremento' : 'descenso';
    
    return `${icon} ${Math.abs(diff).toFixed(1)}% de ${trend}`;
}
            },
            footerFont: { size: 12, weight: 'bold' },
            footerMarginTop: 10,
            footerColor: '#ffffff'
        }
    }, 
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                color: '#8e8e93',
                maxRotation: 45,
                minRotation: 0,
                font: { size: 11 }
            },
            border: { display: false }
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#8e8e93',
                font: { size: 10 },
                callback: (value) => '$' + Number(value).toLocaleString()
            },
            grid: {
                color: 'rgba(142, 142, 147, 0.12)',
                drawBorder: false
            },
            border: { display: false }
        }
    },
    animation: {
        duration: 1000,
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
