<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns;

/**
 * Paleta y opciones Chart.js alineadas con {@see \App\Filament\Business\Resources\Agencies\Widgets\NewRegisterAgencyForMountChart}.
 */
trait AgencyLikeBarChartStyling
{
    /**
     * @return array{fill: string, stroke: string}
     */
    private function glassColorAt(int $index): array
    {
        $palette = [
            ['fill' => 'rgba(48, 209, 88, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.78)'],
            ['fill' => 'rgba(10, 132, 255, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.78)'],
            ['fill' => 'rgba(255, 159, 10, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(191, 90, 242, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(255, 69, 58, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(100, 210, 255, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(255, 214, 10, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.74)'],
            ['fill' => 'rgba(94, 92, 230, 0.76)', 'stroke' => 'rgba(255, 255, 255, 0.72)'],
        ];

        return $palette[$index % count($palette)];
    }

    private function brighterGlassFill(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $m)) {
            $a = min(0.88, (float) $m[4] + 0.18);

            return "rgba({$m[1]}, {$m[2]}, {$m[3]}, {$a})";
        }

        return $rgba;
    }

    /**
     * @param  list<int|float>  $values
     * @return array{fills: list<string>, strokes: list<string>, hovers: list<string>}
     */
    private function glassBarColorsForValues(array $values): array
    {
        $fills = [];
        $strokes = [];
        $hovers = [];
        foreach (array_keys($values) as $i) {
            $c = $this->glassColorAt((int) $i);
            $fills[] = $c['fill'];
            $strokes[] = $c['stroke'];
            $hovers[] = $this->brighterGlassFill($c['fill']);
        }

        return ['fills' => $fills, 'strokes' => $strokes, 'hovers' => $hovers];
    }

    /**
     * @return array<string, mixed>
     */
    protected function agencyStyleVerticalBarChartOptions(): array
    {
        $iosFont = '-apple-system, BlinkMacSystemFont, system-ui, sans-serif';

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'layout' => [
                'padding' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 4,
                    'left' => 4,
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'intersect' => true,
                'axis' => 'xy',
            ],
            'datasets' => [
                'bar' => [
                    'categoryPercentage' => 0.92,
                    'barPercentage' => 0.98,
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderWidth' => 1.25,
                    'borderRadius' => 10,
                    'inflateAmount' => 0.6,
                    'hoverBorderWidth' => 2.5,
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.92)',
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                    'position' => 'nearest',
                    'xAlign' => 'center',
                    'yAlign' => 'bottom',
                    'backgroundColor' => 'rgba(22, 22, 24, 0.56)',
                    'titleColor' => '#f5f5f7',
                    'bodyColor' => 'rgba(235, 235, 245, 0.88)',
                    'footerColor' => 'rgba(235, 235, 245, 0.7)',
                    'borderColor' => 'rgba(255, 255, 255, 0.2)',
                    'borderWidth' => 1,
                    'padding' => 10,
                    'cornerRadius' => 12,
                    'caretSize' => 6,
                    'caretPadding' => 8,
                    'titleFont' => [
                        'size' => 14,
                        'weight' => '700',
                        'family' => $iosFont,
                    ],
                    'bodyFont' => [
                        'size' => 13,
                        'weight' => '500',
                        'family' => $iosFont,
                    ],
                    'titleSpacing' => 0,
                    'titleMarginBottom' => 8,
                    'bodySpacing' => 6,
                    'footerSpacing' => 8,
                    'displayColors' => true,
                    'usePointStyle' => true,
                    'boxWidth' => 12,
                    'boxHeight' => 12,
                    'boxPadding' => 8,
                    'multiKeyBackground' => 'rgba(255, 255, 255, 0.08)',
                ],
            ],
            'scales' => [
                'x' => [
                    'offset' => true,
                    'stacked' => false,
                    'grid' => [
                        'display' => true,
                        'drawBorder' => false,
                        'color' => 'rgba(120, 120, 128, 0.1)',
                    ],
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                        'color' => '#8e8e93',
                        'font' => [
                            'size' => 10,
                            'family' => $iosFont,
                        ],
                    ],
                ],
                'y' => [
                    'stacked' => false,
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                        'drawBorder' => false,
                        'color' => 'rgba(120, 120, 128, 0.12)',
                    ],
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                        'color' => '#8e8e93',
                        'font' => [
                            'size' => 10,
                            'family' => $iosFont,
                        ],
                    ],
                ],
            ],
            'animation' => [
                'duration' => 900,
                'easing' => 'easeOutQuart',
            ],
        ];
    }
}
