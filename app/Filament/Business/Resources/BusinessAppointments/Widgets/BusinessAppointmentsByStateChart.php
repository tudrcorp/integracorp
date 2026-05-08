<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Widgets;

use App\Filament\Business\Resources\BusinessAppointments\Pages\ListBusinessAppointments;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class BusinessAppointmentsByStateChart extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use InteractsWithPageTable;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected ?string $heading = 'Segmentación por estado';

    protected ?string $description = 'Top de estados con mayor volumen de citas registradas.';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public ?int $chartYear = null;

    public ?int $chartMonth = null;

    protected function getTablePage(): string
    {
        return ListBusinessAppointments::class;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        return self::buildChartData((clone $this->getPageTableQuery()));
    }

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    public static function buildChartData(Builder $query): array
    {
        $overallTotal = (clone $query)->reorder()->count();

        $rows = (clone $query)
            ->reorder()
            ->leftJoin('states', 'business_appointments.state_id', '=', 'states.id')
            ->selectRaw("COALESCE(states.definition, 'Sin estado') as state_name, COUNT(*) as total")
            ->groupBy('state_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $values = $rows->pluck('total')->map(static fn (mixed $value): int => (int) $value)->values()->all();
        $colors = (new self)->glassBarColorsForValues($values);

        $labels = $rows->map(static function (mixed $row) use ($overallTotal): string {
            $stateName = (string) data_get($row, 'state_name', 'Sin estado');
            $total = (int) data_get($row, 'total', 0);

            $percentage = $overallTotal > 0 ? round(($total / $overallTotal) * 100, 1) : 0.0;
            $percentageLabel = rtrim(rtrim(number_format($percentage, 1, ',', ''), '0'), ',');

            return "{$stateName} ({$total} - {$percentageLabel}%)";
        })->values()->all();

        return [
            'datasets' => [[
                'label' => 'Citas por estado',
                'data' => $values,
                'backgroundColor' => $colors['fills'],
                'borderColor' => 'transparent',
                'hoverOffset' => 26,
                'hoverBorderWidth' => 3,
                'hoverBorderColor' => '#ffffff',
                'borderRadius' => 4,
                'hoverBackgroundColor' => $colors['hovers'],
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            layout: { padding: 24 },
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        font: { size: 13, weight: '600' },
                        color: 'gray'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#1e293b',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true
                }
            },
            hover: { mode: 'nearest', intersect: true },
            animation: { animateScale: true, animateRotate: true, duration: 900, easing: 'easeOutQuart' },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            }
        }
        JS);
    }
}
