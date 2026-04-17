<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Models\Agency;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class NewRegisterAgencyForMountChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.new-register-agency-month-chart';

    protected ?string $heading = 'Agencias registradas por mes';

    protected ?string $maxHeight = '440px';

    protected int|string|array $columnSpan = 1;

    protected string $color = 'gray';

    public ?string $filter = null;

    public function mount(): void
    {
        $this->filter = (string) now()->year;
    }

    protected function getFilters(): ?array
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

    public function getRegistrationsTotalInCurrentView(): int
    {
        $activeFilter = $this->filter ?: (string) now()->year;
        $year = (int) $activeFilter;
        $total = 0;

        for ($month = 1; $month <= 12; $month++) {
            $total += $this->countAgenciesRegisteredForMonth(Carbon::create($year, $month, 1));
        }

        return $total;
    }

    public function getEmptyRegistrationsMessage(): string
    {
        return 'No hay agencias registradas en '.($this->filter ?: now()->year).'.';
    }

    private function glassColorAt(int $index): array
    {
        $palette = [
            ['fill' => 'rgba(48, 209, 88, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.78)'],
            ['fill' => 'rgba(10, 132, 255, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.78)'],
            ['fill' => 'rgba(255, 159, 10, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(191, 90, 242, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(255, 69, 58, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(100, 210, 255, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(255, 214, 10, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.74)'],
            ['fill' => 'rgba(94, 92, 230, 0.76)', 'stroke' => 'rgba(255, 255, 255, 0.72)'],
        ];

        return $palette[$index % count($palette)];
    }

    private function brighterGlassFill(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $m)) {
            $a = min(0.88, (float) $m[4] + 0.18);

            return "rgba({$m[1]}, {$m[2]}, {$m[3]}, {$a})";
        }

        return $rgba;
    }

    public function getDescription(): ?string
    {
        return 'Por fecha de registro de la agencia; si no existe, se usa la fecha de creación del registro.';
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter ?: (string) now()->year;
        $year = (int) $activeFilter;

        $labels = [];
        $values = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
            $labels[] = ucfirst($monthStart->locale('es')->translatedFormat('M'));
            $values[] = $this->countAgenciesRegisteredForMonth($monthStart);
        }

        $fills = [];
        $strokes = [];
        $hovers = [];

        foreach (array_keys($values) as $i) {
            $c = $this->glassColorAt((int) $i);
            $fills[] = $c['fill'];
            $strokes[] = $c['stroke'];
            $hovers[] = $this->brighterGlassFill($c['fill']);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Agencias registradas',
                    'data' => $values,
                    'backgroundColor' => $fills,
                    'borderColor' => $strokes,
                    'borderWidth' => 1.75,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $hovers,
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
                    return ` ${Number(v).toLocaleString()} agencias`;
                }
            }
        }
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                color: '#8e8e93',
                maxRotation: 0,
                autoSkip: false
            },
            border: { display: false }
        },
        y: {
            beginAtZero: true,
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

    protected function getType(): string
    {
        return 'bar';
    }

    private function countAgenciesRegisteredForMonth(Carbon $monthStart): int
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        return Agency::query()
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query
                    ->where(function ($q) use ($monthStart, $monthEnd): void {
                        $q->whereNotNull('date_register')
                            ->where('date_register', '!=', '')
                            ->whereDate('date_register', '>=', $monthStart->toDateString())
                            ->whereDate('date_register', '<=', $monthEnd->toDateString());
                    })
                    ->orWhere(function ($q) use ($monthStart, $monthEnd): void {
                        $q->where(function ($inner): void {
                            $inner->whereNull('date_register')->orWhere('date_register', '=', '');
                        })
                            ->whereBetween('created_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()]);
                    });
            })
            ->count();
    }
}
