<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets;

use App\Support\IndicadoresDeDesempeno\ColaboradoresHelpdeskTicketsChartSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ColaboradoresHelpdeskTicketsChart extends ChartWidget
{
    protected string $view = 'filament.operations.indicadores-de-desempeno-chart';

    protected ?string $heading = 'Tickets creados por colaborador';

    protected ?string $description = 'Tickets creados por los mismos responsables del gráfico de observaciones de proveedores.';

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
        $series = ColaboradoresHelpdeskTicketsChartSeries::totalsByColaborador($this->resolvedYear());
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
                    'label' => 'Tickets creados',
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
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
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
