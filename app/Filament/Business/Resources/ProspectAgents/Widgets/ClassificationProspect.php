<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Concerns\HasProspectResourceChartTimeStateFilters;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Models\ProspectAgent;
use Filament\Widgets\ChartWidget;

class ClassificationProspect extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use HasProspectResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Empresas por clasificación';

    protected ?string $description = 'Total de empresas (prospectos) registradas según su clasificación. Top 25.';

    protected ?string $maxHeight = '400px';

    public function mount(): void
    {
        parent::mount();
        $this->bootProspectChartFilters();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        $distribution = ProspectAgent::query()
            ->selectRaw('COALESCE(NULLIF(TRIM(classification), \'\'), \'Sin clasificación\') as classification_label, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->groupBy('classification_label')
            ->orderByDesc('total')
            ->limit(25)
            ->pluck('total', 'classification_label')
            ->toArray();

        $labels = [];
        $values = [];
        foreach ($distribution as $classification => $total) {
            $labels[] = (string) $classification;
            $values[] = (int) $total;
        }

        $fills = [
            'rgba(0, 122, 255, 0.82)',
            'rgba(10, 132, 255, 0.82)',
            'rgba(64, 156, 255, 0.8)',
            'rgba(90, 200, 250, 0.78)',
            'rgba(0, 64, 221, 0.8)',
            'rgba(94, 92, 230, 0.78)',
            'rgba(52, 120, 246, 0.8)',
            'rgba(0, 199, 255, 0.76)',
        ];

        $strokes = array_fill(0, count($values), 'rgba(255, 255, 255, 0.78)');

        $backgroundColors = array_map(
            static fn (int $i): string => $fills[$i % count($fills)],
            array_keys($values)
        );

        $hoverColors = array_map(
            fn (string $rgba): string => $this->brighterBlueHover($rgba),
            $backgroundColors
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Empresas',
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $strokes,
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $hoverColors,
                ],
            ],
        ];
    }

    private function brighterBlueHover(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $m)) {
            $a = min(0.9, (float) $m[4] + 0.16);

            return "rgba({$m[1]}, {$m[2]}, {$m[3]}, {$a})";
        }

        return $rgba;
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
}
