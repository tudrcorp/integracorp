<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Widgets\Concerns\HasYearMonthChartFilters;
use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TotalSaleForAgent extends ChartWidget
{
    use HasYearMonthChartFilters;
    use IosLiquidGlassBarChartWidget;

    protected string $view = 'filament.widgets.ios-liquid-glass-bar-chart-widget';

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Total de ventas por agente';

    public function mount(): void
    {
        $this->applyDefaultYearMonthForMount();
        parent::mount();
    }

    public function getDescription(): ?string
    {
        [$y, $m] = $this->resolveSelectedYearMonth();
        $label = $m === null
            ? "Todo el año {$y}"
            : ucfirst(Carbon::createFromDate($y, $m, 1)->translatedFormat('F Y'));

        return "Suma de total_amount (US$) por agente en {$label}. Usa los filtros de año y mes.";
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin ventas en el periodo';
    }

    public function getIosBarChartEmptyBody(): string
    {
        [$y, $m] = $this->resolveSelectedYearMonth();
        $label = $m === null
            ? "Todo el año {$y}"
            : ucfirst(Carbon::createFromDate($y, $m, 1)->translatedFormat('F Y'));

        return "No hay ventas registradas para los agentes en {$label}. Prueba otro mes o año.";
    }

    protected function getData(): array
    {
        [$year, $month] = $this->resolveSelectedYearMonth();
        if ($month === null) {
            $start = Carbon::createFromDate($year, 1, 1)->startOfDay();
            $end = Carbon::createFromDate($year, 12, 31)->endOfDay();
        } else {
            $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $end = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();
        }

        $salesQuery = Sale::query()
            ->select([
                'agents.name as label',
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
            ])
            ->join('agents', 'agents.id', '=', 'sales.agent_id')
            ->whereBetween('sales.created_at', [$start, $end]);

        $salesData = $salesQuery
            ->groupBy('agents.id', 'agents.name')
            ->havingRaw('COALESCE(SUM(sales.total_amount), 0) > 0')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        $labels = $salesData->pluck('label')->all();
        $values = $salesData->pluck('total')->map(fn ($v) => round((float) $v, 2))->all();

        $backgroundColors = [];
        $borderColors = [];
        $hoverBackgroundColors = [];

        foreach (array_keys($labels) as $index) {
            // Color principal solicitado
            $backgroundColors[] = '#2d89ca';

            // Borde ligeramente más oscuro para dar definición
            $borderColors[] = '#246da2';

            // Efecto hover (puedes usar tu función existente o un color fijo)
            $hoverBackgroundColors[] = '#3b9ad9';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total de ventas (US$)',
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1.75,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $hoverBackgroundColors,
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
        legend: { display: false },
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
                    return ' US$ ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    protected function glassColorAt(int $index, float $alpha = 0.9): string
    {
        $palette = [
            [16, 185, 129],
            [59, 130, 246],
            [168, 85, 247],
            [249, 115, 22],
            [236, 72, 153],
            [20, 184, 166],
            [99, 102, 241],
            [239, 68, 68],
        ];

        [$r, $g, $b] = $palette[$index % count($palette)];

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    protected function glassStrokeAt(int $index): string
    {
        $palette = [
            [5, 150, 105],
            [29, 78, 216],
            [126, 34, 206],
            [194, 65, 12],
            [190, 24, 93],
            [15, 118, 110],
            [67, 56, 202],
            [185, 28, 28],
        ];

        [$r, $g, $b] = $palette[$index % count($palette)];

        return "rgba({$r}, {$g}, {$b}, 1)";
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
