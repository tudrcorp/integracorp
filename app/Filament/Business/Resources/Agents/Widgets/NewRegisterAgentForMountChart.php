<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Models\Agent;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class NewRegisterAgentForMountChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Agentes registrados por mes';

    protected ?string $description = 'Cantidad de agentes registrados por mes en el año seleccionado.';

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected ?string $pollingInterval = null;

    public ?string $filter = null;

    public function mount(): void
    {
        $this->filter = (string) now()->year;
    }

    public function getFilters(): ?array
    {
        $currentYear = (int) now()->year;
        $years = range($currentYear, $currentYear - 5);

        return collect($years)
            ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
            ->all();
    }

    public function updatedFilter(): void
    {
        $this->updateChartData();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        if ($activeFilter === null || $activeFilter === '') {
            $activeFilter = (string) now()->year;
        }

        $year = (int) $activeFilter;

        $labels = [];
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $labels[] = Carbon::createFromDate($year, $month, 1)->translatedFormat('M');
            $data[] = $this->countAgentsRegisteredForMonth($year, $month);
        }

        $backgroundColors = [];
        $borderColors = [];
        $hoverBackgroundColors = [];

        foreach ($data as $index => $value) {
            $backgroundColors[] = $this->glassColorAt($index);
            $borderColors[] = $this->glassStrokeAt($index);
            $hoverBackgroundColors[] = $this->brighterGlassFill($this->glassColorAt($index));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Agentes registrados',
                    'data' => $data,
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
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
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
            displayColors: false,
            callbacks: {
                label: (ctx) => {
                    const v = ctx.parsed?.y;
                    if (v === null || v === undefined) return '';
                    return ` ${Number(v).toLocaleString()} agentes`;
                }
            }
        }
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
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                color: '#8e8e93',
                maxRotation: 0,
                autoSkip: true
            },
            border: { display: false }
        },
        y: {
            beginAtZero: true,
            suggestedMax: 4,
            ticks: {
                precision: 0,
                color: '#8e8e93',
                callback: (value) => Number(value).toLocaleString()
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

    public function getRegistrationsTotalInCurrentView(): int
    {
        $activeFilter = $this->filter;

        if ($activeFilter === null || $activeFilter === '') {
            $activeFilter = (string) now()->year;
        }

        $year = (int) $activeFilter;

        $total = 0;

        for ($month = 1; $month <= 12; $month++) {
            $total += $this->countAgentsRegisteredForMonth($year, $month);
        }

        return $total;
    }

    public function getEmptyRegistrationsMessage(): string
    {
        $activeFilter = $this->filter;

        if ($activeFilter === null || $activeFilter === '') {
            $activeFilter = (string) now()->year;
        }

        return "No hay agentes registrados en {$activeFilter}.";
    }

    protected function countAgentsRegisteredForMonth(int $year, int $month): int
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return (int) Agent::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    protected int|string|array $columnSpan = 'full';

    protected function getMaxHeight(): ?string
    {
        return $this->maxHeight;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public static function canView(): bool
    {
        return true;
    }

    public static function getSort(): int
    {
        return 2;
    }

    protected string $view = 'filament.widgets.new-register-agent-month-chart';
}
