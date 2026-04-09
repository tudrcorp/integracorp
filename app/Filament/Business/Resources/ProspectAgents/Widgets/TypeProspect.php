<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Models\ProspectAgent;
use Filament\Widgets\ChartWidget;

class TypeProspect extends ChartWidget
{
    use AgencyLikeBarChartStyling;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Prospectos por tipo';

    protected ?string $description = 'Total de prospectos registrados por tipo de prospecto.';

    protected ?string $maxHeight = '320px';

    private const TYPE_LABELS = [
        'agencia-corretaje' => 'Agencia (corretaje)',
        'agente-corretaje' => 'Agente (Corretaje)',
        'agencia-viajes' => 'Agencia de Viajes',
        'mayorista-viajes' => 'Mayorista de Viajes',
        'freelance' => 'Freelance',
        'asesor-exclusivo' => 'Asesor exclusivo',
        'cliente-individual' => 'Cliente Individual',
        'cliente-corporativo' => 'Cliente Corporativo',
        'ejecutivo' => 'Ejecutivo',
        'otro' => 'Otro',
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $distribution = ProspectAgent::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type')
            ->toArray();

        $labels = [];
        $values = [];
        foreach ($distribution as $type => $total) {
            $labels[] = self::TYPE_LABELS[$type] ?? $type;
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
