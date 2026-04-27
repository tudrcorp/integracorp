<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Concerns\HasProspectResourceChartTimeStateFilters;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Support\ProspectAgents\ProspectCollaboratorLabels;
use Filament\Widgets\ChartWidget;

class TopRegisterProspect extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use HasProspectResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Prospectos registrados por colaborador';

    protected ?string $description = 'Prospectos registrados agrupados por colaborador (creador del registro).';

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

        $ordered = ProspectCollaboratorLabels::prospectCountsOrdered($year, $month);
        $labels = $ordered['labels'];
        $prospectCounts = $ordered['counts'];
        $n = count($labels);
        $blueFill = 'rgba(10, 132, 255, 0.88)';
        $stroke = 'rgba(255, 255, 255, 0.82)';

        return [
            'datasets' => [
                [
                    'label' => 'Prospectos registrados',
                    'data' => $prospectCounts,
                    'backgroundColor' => array_fill(0, $n, $blueFill),
                    'borderColor' => array_fill(0, $n, $stroke),
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => array_fill(0, $n, $this->brighterGlassFill($blueFill)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return array_replace_recursive($this->agencyStyleVerticalBarChartOptions(), [
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
