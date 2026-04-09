<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Models\Agency;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class NewRegisterAgencyForMountChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.new-register-agency-month-chart';

    protected ?string $heading = 'Agencias registradas por mes';

    protected ?string $maxHeight = '440px';

    protected int|string|array $columnSpan = 'full';

    protected string $color = 'gray';

    public ?string $filter = '12m';

    public function updatedFilter(?string $value): void
    {
        $this->cachedData = null;
    }

    /**
     * Suma de registros mostrados en el periodo seleccionado (para estado vacío).
     */
    public function getRegistrationsTotalInCurrentView(): int
    {
        $data = $this->getCachedData();

        return (int) collect($data['datasets'][0]['data'] ?? [])->sum();
    }

    public function getEmptyRegistrationsMessage(): string
    {
        return ($this->filter ?? '12m') === 'year'
            ? 'No hay agencias registradas en el año en curso.'
            : 'No hay agencias registradas en los últimos 12 meses.';
    }

    /**
     * Misma paleta y borde que App\Filament\Operations\Resources\Suppliers\Widgets\SupplierClasificationChart::glassColorAt().
     *
     * @return array{fill: string, stroke: string}
     */
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

    protected function getFilters(): ?array
    {
        return [
            '12m' => 'Últimos 12 meses',
            'year' => 'Año en curso',
        ];
    }

    public function getDescription(): ?string
    {
        return 'Por fecha de registro de la agencia; si no existe, se usa la fecha de creación del registro.';
    }

    protected function getData(): array
    {
        $mode = $this->filter ?? '12m';
        $labels = [];
        $values = [];

        if ($mode === 'year') {
            $year = (int) now()->year;
            for ($month = 1; $month <= 12; $month++) {
                $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
                $labels[] = $monthStart->locale('es')->translatedFormat('M');
                if ($monthStart->isFuture()) {
                    $values[] = 0;
                } else {
                    $values[] = $this->countAgenciesRegisteredForMonth($monthStart);
                }
            }
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = now()->copy()->subMonths($i)->startOfMonth();
                $labels[] = $monthStart->locale('es')->translatedFormat('M y');
                $values[] = $this->countAgenciesRegisteredForMonth($monthStart);
            }
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
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $hovers,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Alineado con SupplierClasificationChart::getOptions() (barras verticales: meses en eje X).
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $iosFont = '-apple-system, BlinkMacSystemFont, system-ui, sans-serif';

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'layout' => [
                'padding' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 4,
                    'left' => 4,
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'intersect' => true,
                'axis' => 'xy',
            ],
            'datasets' => [
                'bar' => [
                    'categoryPercentage' => 0.92,
                    'barPercentage' => 0.98,
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderWidth' => 1.25,
                    'borderRadius' => 10,
                    'inflateAmount' => 0.6,
                    'hoverBorderWidth' => 2.5,
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.92)',
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                    'position' => 'nearest',
                    'xAlign' => 'center',
                    'yAlign' => 'bottom',
                    'backgroundColor' => 'rgba(22, 22, 24, 0.56)',
                    'titleColor' => '#f5f5f7',
                    'bodyColor' => 'rgba(235, 235, 245, 0.88)',
                    'footerColor' => 'rgba(235, 235, 245, 0.7)',
                    'borderColor' => 'rgba(255, 255, 255, 0.2)',
                    'borderWidth' => 1,
                    'padding' => 10,
                    'cornerRadius' => 12,
                    'caretSize' => 6,
                    'caretPadding' => 8,
                    'titleFont' => [
                        'size' => 14,
                        'weight' => '700',
                        'family' => $iosFont,
                    ],
                    'bodyFont' => [
                        'size' => 13,
                        'weight' => '500',
                        'family' => $iosFont,
                    ],
                    'titleSpacing' => 0,
                    'titleMarginBottom' => 8,
                    'bodySpacing' => 6,
                    'footerSpacing' => 8,
                    'displayColors' => true,
                    'usePointStyle' => true,
                    'boxWidth' => 12,
                    'boxHeight' => 12,
                    'boxPadding' => 8,
                    'multiKeyBackground' => 'rgba(255, 255, 255, 0.08)',
                ],
            ],
            'scales' => [
                'x' => [
                    'offset' => true,
                    'stacked' => false,
                    'grid' => [
                        'display' => true,
                        'drawBorder' => false,
                        'color' => 'rgba(120, 120, 128, 0.1)',
                    ],
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                        'color' => '#8e8e93',
                        'font' => [
                            'size' => 10,
                            'family' => $iosFont,
                        ],
                    ],
                ],
                'y' => [
                    'stacked' => false,
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                        'drawBorder' => false,
                        'color' => 'rgba(120, 120, 128, 0.12)',
                    ],
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                        'color' => '#8e8e93',
                        'font' => [
                            'size' => 10,
                            'family' => $iosFont,
                        ],
                    ],
                ],
            ],
            'animation' => [
                'duration' => 900,
                'easing' => 'easeOutQuart',
            ],
        ];
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
