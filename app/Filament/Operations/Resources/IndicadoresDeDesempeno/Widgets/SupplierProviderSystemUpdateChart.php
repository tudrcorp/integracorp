<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets;

use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\Concerns\HasIndicadoresDeDesempenoMonthlyDrillDown;
use App\Support\IndicadoresDeDesempeno\SupplierProviderSystemUpdateChartSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class SupplierProviderSystemUpdateChart extends ChartWidget
{
    use HasIndicadoresDeDesempenoMonthlyDrillDown;

    protected string $view = 'filament.operations.indicadores-de-desempeno-chart';

    protected ?string $heading = 'Actualización del proveedor en el sistema';

    protected ?string $description = 'Actualizaciones mensuales de proveedores. Haz clic en un mes para ver el desglose semanal, y en una semana para ver el detalle por colaborador.';

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
            $series = SupplierProviderSystemUpdateChartSeries::groupedByCollaboratorForWeek(
                $year,
                $this->selectedMonth,
                $this->selectedWeek,
                $collaborator,
            );
        } elseif ($this->selectedMonth !== null) {
            $series = SupplierProviderSystemUpdateChartSeries::groupedByWeek($year, $this->selectedMonth, $collaborator);
        } else {
            $series = SupplierProviderSystemUpdateChartSeries::groupedByMonth($year, $collaborator);
        }

        $juridicosFill = 'rgba(88, 86, 214, 0.88)';
        $juridicosStroke = 'rgba(255, 255, 255, 0.82)';
        $naturalesFill = 'rgba(255, 45, 85, 0.88)';
        $naturalesStroke = 'rgba(255, 255, 255, 0.82)';

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => SupplierProviderSystemUpdateChartSeries::LABEL_JURIDICOS,
                    'data' => $series['juridicos'],
                    'backgroundColor' => $juridicosFill,
                    'borderColor' => $juridicosStroke,
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($juridicosFill),
                ],
                [
                    'label' => SupplierProviderSystemUpdateChartSeries::LABEL_NATURALES,
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
