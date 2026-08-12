<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets;

use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\Concerns\HasIndicadoresDeDesempenoMonthlyDrillDown;
use App\Support\IndicadoresDeDesempeno\ColaboradoresHelpdeskTicketsChartSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ColaboradoresHelpdeskTicketsChart extends ChartWidget
{
    use HasIndicadoresDeDesempenoMonthlyDrillDown;

    protected string $view = 'filament.operations.indicadores-de-desempeno-chart';

    protected ?string $heading = 'Tickets creados por mes';

    protected ?string $description = 'Totales mensuales de tickets creados. Haz clic en un mes para ver el desglose semanal, y en una semana para ver el detalle por colaborador.';

    protected ?string $maxHeight = '480px';

    protected int|string|array $columnSpan = 'full';

    protected string $color = 'gray';

    protected function getFilters(): ?array
    {
        return $this->yearFilters();
    }

    protected function getData(): array
    {
        $year = $this->resolvedYear();
        $collaborator = $this->scopedCollaborator();

        if ($this->selectedMonth !== null && $this->selectedWeek !== null) {
            $series = ColaboradoresHelpdeskTicketsChartSeries::totalsByCollaboratorForWeek(
                $year,
                $this->selectedMonth,
                $this->selectedWeek,
                $collaborator,
            );
            $label = "Tickets · {$this->selectedWeekLabel()} · {$this->selectedMonthLabel()} {$year}";
        } elseif ($this->selectedMonth !== null) {
            $series = ColaboradoresHelpdeskTicketsChartSeries::totalsByWeek($year, $this->selectedMonth, $collaborator);
            $label = "Tickets · {$this->selectedMonthLabel()} {$year}";
        } else {
            $series = ColaboradoresHelpdeskTicketsChartSeries::totalsByMonth($year, $collaborator);
            $label = "Tickets creados ({$year})";
        }

        $labels = $series['labels'];
        $totals = $series['totals'];
        $count = count($labels);

        $palette = [
            ['fill' => 'rgba(48, 209, 88, 0.88)', 'stroke' => 'rgba(255, 255, 255, 0.82)'],
            ['fill' => 'rgba(10, 132, 255, 0.88)', 'stroke' => 'rgba(255, 255, 255, 0.82)'],
        ];

        $fills = [];
        $strokes = [];
        $hovers = [];

        for ($index = 0; $index < $count; $index++) {
            $color = $palette[$index % count($palette)];
            $fills[] = $color['fill'];
            $strokes[] = $color['stroke'];
            $hovers[] = $this->brighterGlassFill($color['fill']);
        }

        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $totals,
                    'backgroundColor' => $fills,
                    'borderColor' => $strokes,
                    'borderWidth' => 1.25,
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $hovers,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return $this->monthlyDrillDownOptions(showLegend: false);
    }
}
