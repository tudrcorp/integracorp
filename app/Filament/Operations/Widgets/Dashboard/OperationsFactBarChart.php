<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

abstract class OperationsFactBarChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, int>
     */
    abstract protected function counts(): array;

    abstract protected function tooltipNoun(): string;

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = $this->counts();
        $labels = array_keys($counts);
        $values = array_values($counts);

        return [
            'datasets' => [
                [
                    'label' => $this->tooltipNoun(),
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
            '#1f6fa8', '#247db8', '#2d89ca', '#3b9fd4', '#4ab5de',
        ];

        $colors = [];

        for ($index = 0; $index < $count; $index++) {
            $colors[] = $palette[$index % count($palette)];
        }

        return $colors;
    }

    protected function getOptions(): RawJs
    {
        $noun = $this->tooltipNoun();

        return RawJs::make(<<<JS
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
                            return ' {$noun}: ' + context.raw;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        autoSkip: false
                    },
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
