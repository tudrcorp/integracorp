<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Widgets;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentLabels;
use App\Filament\Business\Resources\BusinessAppointments\Pages\ListBusinessAppointments;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class BusinessAppointmentsByStatusChart extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use InteractsWithPageTable;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected ?string $heading = 'Segmentación por estatus / gestión';

    protected ?string $description = 'Distribución de citas por estado de gestión.';

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
        return 'bar';
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
        $statusOptions = BusinessAppointmentLabels::statusOptions();

        /** @var array<string, int> $totalsByStatus */
        $totalsByStatus = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        $labels = [];
        $data = [];

        foreach ($statusOptions as $key => $label) {
            $labels[] = $label;
            $data[] = $totalsByStatus[$key] ?? 0;
        }

        // Conserva la paleta vibrante que veníamos usando en el doughnut.
        $vibrantPalette = [
            '#FF2D55',
            '#34C759',
            '#FF3B30',
            '#007AFF',
        ];

        return [
            'datasets' => [[
                'label' => 'Citas',
                'data' => $data,
                'backgroundColor' => $vibrantPalette,
                'borderColor' => 'transparent',
                'borderWidth' => 1.25,
                'borderRadius' => 10,
                'borderSkipped' => false,
                'hoverBackgroundColor' => $vibrantPalette,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return $this->agencyStyleVerticalBarChartOptions();
    }
}
