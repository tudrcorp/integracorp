<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Models\ProspectAgent;
use Filament\Widgets\ChartWidget;

class ReferenceProspect extends ChartWidget
{
    use AgencyLikeBarChartStyling;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Prospectos por referido';

    protected ?string $description = 'Total de prospectos registrados por canal de referencia.';

    protected ?string $maxHeight = '320px';

    private const REFERENCE_LABELS = [
        'directiva-TDG' => 'Directiva TDG',
        'gerencia-de-negocios' => 'Gerencia de Negocios',
        'whatsapp-comercial' => 'Whatsapp Comercial',
        'redes-sociales' => 'Redes sociales',
        'tercero' => 'Tercero',
        'otro' => 'Otro',
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $distribution = ProspectAgent::query()
            ->selectRaw('reference_by, COUNT(*) as total')
            ->groupBy('reference_by')
            ->orderByDesc('total')
            ->pluck('total', 'reference_by')
            ->toArray();

        $labels = [];
        $values = [];
        foreach ($distribution as $referenceBy => $total) {
            $labels[] = self::REFERENCE_LABELS[$referenceBy] ?? $referenceBy;
            $values[] = (int) $total;
        }

        $colors = $this->glassBarColorsForValues($values);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Prospectos',
                    'data' => $values,
                    'backgroundColor' => $colors['fills'],
                    'borderColor' => $colors['strokes'],
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $colors['hovers'],
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->agencyStyleVerticalBarChartOptions();
    }
}
