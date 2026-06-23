<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets;

use App\Support\IndicadoresDeDesempeno\SupplierNewProviderCreationChartSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class SupplierNewProviderCreationChart extends ChartWidget
{
    protected string $view = 'filament.operations.indicadores-de-desempeno-chart';

    protected ?string $heading = 'Creación de un nuevo proveedor';

    protected ?string $description = 'Proveedores nuevos con correo principal registrado (envío de kit por correo). Agrupado por responsable (created_by).';

    protected ?string $maxHeight = '480px';

    protected int|string|array $columnSpan = 'full';

    protected string $color = 'gray';

    protected function getFilters(): ?array
    {
        $now = now();
        $filters = [];

        for ($i = 0; $i < 5; $i++) {
            $y = $now->year - $i;
            $filters[(string) $y] = (string) $y;
        }

        return $filters;
    }

    protected function getData(): array
    {
        $series = SupplierNewProviderCreationChartSeries::groupedByCollaborator($this->resolvedYear());

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
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            datasets: {
                bar: {
                    categoryPercentage: 0.82,
                    barPercentage: 0.92,
                },
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#000000',
                        boxWidth: 12,
                        boxHeight: 12,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            size: 13,
                        },
                    },
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                },
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: '#000000',
                        font: {
                            size: 13,
                        },
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                        color: '#000000',
                        font: {
                            size: 13,
                        },
                    },
                },
            },
        }
        JS);
    }

    private function resolvedYear(): int
    {
        return (int) ($this->filter ?? now()->year);
    }

    private function brighterGlassFill(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $matches)) {
            $alpha = min(0.95, (float) $matches[4] + 0.12);

            return "rgba({$matches[1]}, {$matches[2]}, {$matches[3]}, {$alpha})";
        }

        return $rgba;
    }
}
