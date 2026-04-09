<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Widgets\Concerns\IosLiquidGlassBarChartWidget;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TotalSaleForAgent extends ChartWidget
{
    use IosLiquidGlassBarChartWidget;

    protected string $view = 'filament.widgets.ios-liquid-glass-bar-chart-widget';

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Total de ventas por agente';

    public ?string $filter = 'year';

    public function getDescription(): ?string
    {
        return 'Suma de total_amount (US$) por agente según el periodo seleccionado.';
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin ventas en el periodo';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay ventas registradas para los agentes en el rango de fechas seleccionado.';
    }

    protected function getFilters(): ?array
    {
        return [
            'year' => 'Este año',
            'month' => 'Este mes',
            'week' => 'Esta semana',
            'last_5_days' => 'Últimos 5 días',
        ];
    }

    protected function getData(): array
    {
        $now = Carbon::now();

        $salesQuery = Sale::query()
            ->select([
                'agents.name as label',
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
            ])
            ->join('agents', 'agents.id', '=', 'sales.agent_id');

        if ($this->filter === 'month') {
            $salesQuery
                ->whereMonth('sales.created_at', $now->month)
                ->whereYear('sales.created_at', $now->year);
        } elseif ($this->filter === 'week') {
            $salesQuery->whereBetween('sales.created_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ]);
        } elseif ($this->filter === 'last_5_days') {
            $salesQuery->whereBetween('sales.created_at', [
                $now->copy()->subDays(4)->startOfDay(),
                $now->copy()->endOfDay(),
            ]);
        } elseif ($this->filter === 'year') {
            $salesQuery->whereYear('sales.created_at', $now->year);
        }

        $salesData = $salesQuery
            ->groupBy('agents.id', 'agents.name')
            ->havingRaw('COALESCE(SUM(sales.total_amount), 0) > 0')
            ->orderByDesc('total')
            ->get();

        $labels = $salesData->pluck('label')->all();
        $values = $salesData->pluck('total')->map(fn ($v) => round((float) $v, 2))->all();

        $backgroundColors = [];
        $borderColors = [];
        $hoverBackgroundColors = [];

        foreach (array_keys($labels) as $index) {
            $backgroundColors[] = $this->glassColorAt($index);
            $borderColors[] = $this->glassStrokeAt($index);
            $hoverBackgroundColors[] = $this->brighterGlassFill($this->glassColorAt($index));
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
