<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Models\Agency;
use App\Models\IndividualQuote;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class IndividualQuotesAgencyAverageChart extends ChartWidget
{
    protected ?string $heading = 'TOP 25 AGENCIAS - MAYOR VOLUMEN DE COTIZACIONES';

    protected ?string $description = 'Ranking de las 25 agencias con más cotizaciones generadas.';

    protected ?string $maxHeight = '350px';

    protected int | string | array $columnSpan = 'full';

    /**
     * Genera un color aleatorio vibrante.
     * Se limitan los rangos de RGB para evitar colores demasiado claros (pasteles)
     * o demasiado oscuros que se pierdan en el modo oscuro.
     */
    protected function getRandomVibrantColor(): string
    {
        // Rango medio-alto para asegurar que el color resalte y sea sólido
        $r = mt_rand(40, 220);
        $g = mt_rand(40, 220);
        $b = mt_rand(40, 220);
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    protected function getData(): array
    {
        // Consultamos las 25 agencias que más cotizan agrupando por code_agency
        $topAgencies = IndividualQuote::query()
            ->select('code_agency', DB::raw('count(*) as total'))
            ->whereNotNull('code_agency')
            ->groupBy('code_agency')
            ->orderByDesc('total')
            ->limit(25)
            ->get();

        $labels = [];
        $values = [];
        $backgroundColors = [];

        foreach ($topAgencies as $quoteData) {
            // Buscamos el nombre de la agencia en la tabla 'agencies' usando el código
            $agencyName = Agency::where('code', $quoteData->code_agency)->first()?->name_corporative
                ?? "Agencia: {$quoteData->code_agency}";

            $labels[] = $agencyName;
            $values[] = $quoteData->total;

            // Aplicamos un color vibrante aleatorio por cada barra
            $backgroundColors[] = $this->getRandomVibrantColor();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Cotizaciones',
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 6,
                    'barPercentage' => 0.7,
                    'categoryPercentage' => 0.8,
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
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1d1d1f',
                    bodyColor: '#1d1d1f',
                    borderColor: '#d2d2d7',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true // Habilitamos para ver el color de la barra en el tooltip
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
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 45,
                        font: {
                            size: 10
                        }
                    }
                }
            },
            animation: {
                duration: 1200,
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
