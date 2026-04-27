<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Concerns\HasProspectResourceChartTimeStateFilters;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Support\ProspectAgents\ProspectAgentTaskChartSeries;
use Filament\Widgets\ChartWidget;

class ProspectAgentTasksByUserChart extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use HasProspectResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Tareas por colaborador';

    protected ?string $description = 'Tareas creadas y tareas marcadas como resueltas por usuario (actualización).';

    protected ?string $maxHeight = '400px';

    public function mount(): void
    {
        parent::mount();
        $this->bootProspectChartFilters();
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        $series = ProspectAgentTaskChartSeries::createdAndResolvedByUser($year, $month);
        $labels = $series['labels'];
        $created = $series['created'];
        $resolved = $series['resolved'];

        $n = count($labels);
        $createdFill = 'rgba(255, 214, 10, 0.9)';
        $resolvedFill = 'rgba(48, 209, 88, 0.88)';
        $stroke = 'rgba(255, 255, 255, 0.82)';

        return [
            'datasets' => [
                [
                    'label' => 'Tareas creadas',
                    'data' => $created,
                    'backgroundColor' => array_fill(0, $n, $createdFill),
                    'borderColor' => array_fill(0, $n, $stroke),
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => array_fill(0, $n, $this->brighterGlassFill($createdFill)),
                ],
                [
                    'label' => 'Tareas resueltas',
                    'data' => $resolved,
                    'backgroundColor' => array_fill(0, $n, $resolvedFill),
                    'borderColor' => array_fill(0, $n, $stroke),
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => array_fill(0, $n, $this->brighterGlassFill($resolvedFill)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return array_replace_recursive($this->agencyStyleVerticalBarChartOptions(), [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'color' => '#000000',
                        'font' => [
                            'size' => 13,
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
