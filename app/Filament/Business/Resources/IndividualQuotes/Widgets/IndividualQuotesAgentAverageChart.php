<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Models\Agent;
use App\Models\IndividualQuote;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class IndividualQuotesAgentAverageChart extends ChartWidget
{
    protected ?string $heading = 'TOP 10 AGENTES - MAYOR VOLUMEN DE COTIZACIONES';

    protected ?string $description = 'Ranking de los 10 agentes con más cotizaciones generadas.';

    protected ?string $maxHeight = '320px';

    /**
     * Genera un color pastel aleatorio de cualquier tonalidad.
     * Se utilizan valores altos de RGB (180-255) para garantizar colores claros,
     * vibrantes pero suaves, típicos de la interfaz de iOS.
     */
    protected function getRandomPastelColor(): string
    {
        $r = mt_rand(180, 255);
        $g = mt_rand(180, 255);
        $b = mt_rand(180, 255);
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    protected function getData(): array
    {
        // Consultamos los 10 agentes que más cotizan
        $topAgents = IndividualQuote::query()
            ->select('agent_id', DB::raw('count(*) as total'))
            ->groupBy('agent_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $labels = [];
        $values = [];
        $backgroundColors = [];

        foreach ($topAgents as $quoteData) {
            // Buscamos el nombre del agente en la tabla 'agents'
            $agentName = Agent::find($quoteData->agent_id)?->name ?? "Agente #{$quoteData->agent_id}";

            $labels[] = $agentName;
            $values[] = $quoteData->total;

            // Aplicamos un color pastel aleatorio por cada barra
            $backgroundColors[] = $this->getRandomPastelColor();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Cotizaciones',
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 8,
                    /**
                     * Configuración de ancho de barras Estilo iOS
                     */
                    'barPercentage' => 0.8,
                    'categoryPercentage' => 0.9,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#1d1d1f',
                    bodyColor: '#1d1d1f',
                    borderColor: '#d2d2d7',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: { 
                        stepSize: 1,
                        color: '#86868b' 
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#86868b',
                        font: {
                            size: 11
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
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
