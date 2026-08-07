<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Concerns;

trait BuildsRegistrationMomYearChart
{
    /**
     * @param  list<string>  $labels
     * @param  list<int|float>  $values
     * @return array{
     *     year: int,
     *     data: array{datasets: list<array<string, mixed>>, labels: list<string>},
     *     options: array<string, mixed>
     * }
     */
    protected function buildRegistrationMomYearChart(
        string $accent,
        int $year,
        array $labels,
        array $values,
        string $datasetLabel = 'Captación',
        bool $asFloat = false,
    ): array {
        $palette = match ($accent) {
            'violet' => [
                'border' => 'rgba(139, 92, 246, 1)',
                'background' => 'rgba(139, 92, 246, 0.20)',
                'point' => 'rgba(167, 139, 250, 1)',
            ],
            'emerald' => [
                'border' => 'rgba(16, 185, 129, 1)',
                'background' => 'rgba(16, 185, 129, 0.20)',
                'point' => 'rgba(52, 211, 153, 1)',
            ],
            'rose' => [
                'border' => 'rgba(244, 63, 94, 1)',
                'background' => 'rgba(244, 63, 94, 0.20)',
                'point' => 'rgba(251, 113, 133, 1)',
            ],
            default => [
                'border' => 'rgba(14, 165, 233, 1)',
                'background' => 'rgba(14, 165, 233, 0.20)',
                'point' => 'rgba(56, 189, 248, 1)',
            ],
        };

        $normalizedValues = array_map(
            static function (mixed $value) use ($asFloat): int|float {
                return $asFloat
                    ? round((float) $value, 2)
                    : (int) $value;
            },
            array_values($values),
        );
        $normalizedLabels = array_map(
            static fn (mixed $label): string => (string) $label,
            array_values($labels),
        );

        if (count($normalizedLabels) !== count($normalizedValues)) {
            $count = min(count($normalizedLabels), count($normalizedValues));
            $normalizedLabels = array_slice($normalizedLabels, 0, $count);
            $normalizedValues = array_slice($normalizedValues, 0, $count);
        }

        return [
            'year' => $year,
            'data' => [
                'datasets' => [
                    [
                        'label' => $datasetLabel,
                        'data' => $normalizedValues,
                        'borderColor' => $palette['border'],
                        'backgroundColor' => $palette['background'],
                        'pointBackgroundColor' => $palette['point'],
                        'pointBorderColor' => $palette['border'],
                        'fill' => 'start',
                        'tension' => 0.35,
                        'pointRadius' => 3,
                        'pointHoverRadius' => 5,
                        'borderWidth' => 2.5,
                    ],
                ],
                'labels' => $normalizedLabels,
            ],
            'options' => $this->registrationMomYearChartOptions($asFloat),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function registrationMomYearChartOptions(bool $asFloat = false): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                    'displayColors' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                        'drawBorder' => false,
                    ],
                    'ticks' => [
                        'maxRotation' => 0,
                        'autoSkip' => true,
                        'font' => [
                            'size' => 9,
                            'weight' => '600',
                        ],
                        'color' => 'rgba(148, 163, 184, 0.95)',
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grace' => '12%',
                    'grid' => [
                        'color' => 'rgba(148, 163, 184, 0.12)',
                        'drawBorder' => false,
                    ],
                    'ticks' => [
                        'precision' => $asFloat ? 2 : 0,
                        'maxTicks' => 4,
                        'font' => [
                            'size' => 9,
                            'weight' => '600',
                        ],
                        'color' => 'rgba(148, 163, 184, 0.9)',
                    ],
                ],
            ],
            'layout' => [
                'padding' => [
                    'top' => 6,
                    'right' => 4,
                    'bottom' => 0,
                    'left' => 2,
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'animation' => [
                'duration' => 260,
                'easing' => 'easeOutCubic',
            ],
            'transitions' => [
                'active' => [
                    'animation' => [
                        'duration' => 140,
                    ],
                ],
                'resize' => [
                    'animation' => [
                        'duration' => 0,
                    ],
                ],
            ],
            'devicePixelRatio' => 1.5,
        ];
    }

    /**
     * @return array{year: int, labels: list<string>, values: list<int>}
     */
    protected function emptyRegistrationMomYearSeries(int $year, int $throughMonth): array
    {
        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $count = max(1, min(12, $throughMonth));

        return [
            'year' => $year,
            'labels' => array_slice($labels, 0, $count),
            'values' => array_fill(0, $count, 0),
        ];
    }
}
