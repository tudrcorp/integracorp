<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Models\ProspectAgent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopRegisterProspect extends ChartWidget
{
    use AgencyLikeBarChartStyling;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Top 10 colaboradores por prospectos registrados';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $topColaboradores = ProspectAgent::query()
            ->select([
                'created_by as label',
                DB::raw('COUNT(*) as total'),
            ])
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->groupBy('created_by')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $labels = $topColaboradores->pluck('label')->map(fn (?string $name): string => $name ?? 'Sin nombre')->toArray();
        $values = $topColaboradores->pluck('total')->map(fn (mixed $v): int => (int) $v)->toArray();

        $colors = $this->glassBarColorsForValues($values);

        return [
            'datasets' => [
                [
                    'label' => 'Prospectos registrados',
                    'data' => $values,
                    'backgroundColor' => $colors['fills'],
                    'borderColor' => $colors['strokes'],
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $colors['hovers'],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return $this->agencyStyleVerticalBarChartOptions();
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
