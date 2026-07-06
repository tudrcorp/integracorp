<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\OperationsDashboardMetrics;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class FinishedServicesMonthlyChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected ?string $heading = 'SERVICIOS ATENDIDOS (FINALIZADOS)';

    protected ?string $description = 'Total mensual de coordinaciones de servicio finalizadas en el año seleccionado.';

    protected ?string $maxHeight = '420px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = null;

    public function __construct()
    {
        $this->filter = (string) now()->year;
    }

    /**
     * @return array<int, string>|null
     */
    protected function getFilters(): ?array
    {
        $years = [];
        $currentYear = now()->year;

        for ($index = 0; $index < 5; $index++) {
            $year = $currentYear - $index;
            $years[$year] = (string) $year;
        }

        return $years;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        $dataTrend = Trend::query(
            OperationsDashboardMetrics::coordinationServicesQuery()
                ->where('status', 'FINALIZADO')
        )
            ->between(
                start: Carbon::create($year)->startOfYear(),
                end: Carbon::create($year)->endOfYear()
            )
            ->perMonth()
            ->count();

        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $values = $dataTrend->map(fn (TrendValue $value): int => (int) $value->aggregate)->toArray();

        return [
            'datasets' => [
                [
                    'label' => "Servicios finalizados ({$year})",
                    'data' => $values,
                    'backgroundColor' => $this->buildBackgroundColors(count($values)),
                    'borderColor' => 'rgba(0,0,0,0.08)',
                    'borderWidth' => 1.25,
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function buildBackgroundColors(int $count): array
    {
        $palette = [
            '#2d89ca', '#3b9fd4', '#4ab5de', '#59cbe8', '#68e1f2',
            '#2d89ca', '#3b9fd4', '#4ab5de', '#59cbe8', '#68e1f2',
            '#2d89ca', '#3b9fd4',
        ];

        $colors = [];

        for ($index = 0; $index < $count; $index++) {
            $colors[] = $palette[$index % count($palette)];
        }

        return $colors;
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                    callbacks: {
                        label: function(context) {
                            return ' Servicios: ' + context.raw;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.1)'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, stepSize: 1 },
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.12)'
                    }
                }
            },
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
