<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Concerns\HasProspectResourceChartTimeStateFilters;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Models\ProspectAgent;
use Filament\Widgets\ChartWidget;

class ReferenceProspect extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use HasProspectResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Prospectos por referido';

    protected ?string $description = 'Total de prospectos registrados por canal de referencia.';

    protected ?string $maxHeight = '400px';

    public function mount(): void
    {
        parent::mount();
        $this->bootProspectChartFilters();
    }

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
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        $distribution = ProspectAgent::query()
            ->selectRaw('reference_by, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
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
