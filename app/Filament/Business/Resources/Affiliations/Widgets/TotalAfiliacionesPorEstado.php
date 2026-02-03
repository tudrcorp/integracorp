<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Models\Affiliation;
use App\Models\State;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TotalAfiliacionesPorEstado extends ChartWidget
{

    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliations::class;
    }
    protected ?string $heading = 'RESUMEN DE AFILIACIONES POR ESTADO';

    protected ?string $description = 'Visualización mensual de afiliaciones totales por estado. Solo se muestran las afiliaciones en estatus ACTIVA.';

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    /**
     * Optimizamos la carga de datos usando agregación de Base de Datos.
     * En lugar de Trend (que es para series temporales), usamos GroupBy.
     */
    protected function getData(): array
    {
        $userId = Auth::id();

        // Obtenemos el conteo agrupado directamente desde la base de datos
        // Asumiendo que 'state_id' es la FK en la tabla affiliations
        $stats = $this->getPageTableQuery()
            ->reorder()
            ->select('state_id_ti', DB::raw('count(*) as total'))
            ->where('status', 'ACTIVA')
            ->groupBy('state_id_ti')
            ->pluck('total', 'state_id_ti');

        $minimalistColors = [
            '#94a3b8',
            '#93c5fd',
            '#60a5fa',
            '#3b82f6',
            '#2563eb',
            '#1d4ed8',
            '#1e40af',
            '#1e3a8a',
            '#64748b',
            '#475569',
            '#334155',
            '#0f172a'
        ];

        // Obtenemos todos los estados para asegurar que el gráfico tenga etiquetas coherentes
        $allStates = State::all(['id', 'definition']);

        $labels = [];
        $values = [];

        foreach ($allStates as $state) {
            $labels[] = $state->definition;
            $values[] = $stats->get($state->id, 0); // Si no hay registros, ponemos 0
        }

        return [
            'datasets' => [
                [
                    'label' => 'Afiliaciones',
                    'data' => $values,
                    'backgroundColor' => $this->getChartColors(),
                    'borderColor' => 'rgba(255, 255, 255, 0.5)',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Paleta de colores optimizada para 24 estados
     */
    protected function getChartColors(): array
    {
        return [
            '#94a3b8',
            '#93c5fd',
            '#60a5fa',
            '#3b82f6',
            '#2563eb',
            '#1d4ed8',
            '#1e40af',
            '#1e3a8a',
            '#64748b',
            '#475569',
            '#334155',
            '#0f172a'
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
