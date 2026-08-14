<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets;

use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\Concerns\HasIndicadoresDeDesempenoMonthlyDrillDown;
use App\Support\IndicadoresDeDesempeno\SupplierNewProviderCreationChartSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class SupplierNewProviderCreationChart extends ChartWidget
{
    use HasIndicadoresDeDesempenoMonthlyDrillDown;

    protected string $view = 'filament.operations.indicadores-de-desempeno-chart';

    protected ?string $heading = 'Creación de un nuevo proveedor';

    protected ?string $description = 'Proveedores nuevos con correo principal (envío de kit). Vista mensual; haz clic en un mes para el desglose semanal, y en una semana para el detalle por colaborador.';

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
            $series = SupplierNewProviderCreationChartSeries::groupedByCollaboratorForWeek(
                $year,
                $this->selectedMonth,
                $this->selectedWeek,
                $collaborator,
            );
        } elseif ($this->selectedMonth !== null) {
            $series = SupplierNewProviderCreationChartSeries::groupedByWeek($year, $this->selectedMonth, $collaborator);
        } else {
            $series = SupplierNewProviderCreationChartSeries::groupedByMonth($year, $collaborator);
        }

        $juridicosFill = 'rgba(52, 199, 89, 0.88)';
        $juridicosStroke = 'rgba(255, 255, 255, 0.82)';
        $naturalesFill = 'rgba(0, 199, 190, 0.88)';
        $naturalesStroke = 'rgba(255, 255, 255, 0.82)';

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => SupplierNewProviderCreationChartSeries::LABEL_JURIDICOS,
                    'data' => $series['juridicos'],
                    'backgroundColor' => $juridicosFill,
                    'borderColor' => $juridicosStroke,
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($juridicosFill),
                ],
                [
                    'label' => SupplierNewProviderCreationChartSeries::LABEL_NATURALES,
                    'data' => $series['naturales'],
                    'backgroundColor' => $naturalesFill,
                    'borderColor' => $naturalesStroke,
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($naturalesFill),
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return $this->monthlyDrillDownOptions(showLegend: true);
    }
}
