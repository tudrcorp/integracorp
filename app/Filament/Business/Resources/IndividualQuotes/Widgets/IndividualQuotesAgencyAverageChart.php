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
     * Filtro de años: Año actual + 4 anteriores.
     */
    protected function getFilters(): ?array
    {
        $year = now()->year;
        $filters = [];

        for ($i = 0; $i < 5; $i++) {
            $yearValue = $year - $i;
            $filters[$yearValue] = (string) $yearValue;
        }

        return $filters;
    }

    /**
     * Genera un color aleatorio vibrante.
     */
    protected function getRandomVibrantColor(): string
    {
        $r = mt_rand(40, 220);
        $g = mt_rand(40, 220);
        $b = mt_rand(40, 220);
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    protected function getData(): array
    {
        // Obtenemos el año seleccionado del filtro, por defecto el actual
        $activeFilter = $this->filter ?? now()->year;

        // Consultamos las 25 agencias filtrando por el año de creación
        $topAgencies = IndividualQuote::query()
            ->select('code_agency', DB::raw('count(*) as total'))
            ->whereNotNull('code_agency')
            ->whereYear('created_at', $activeFilter)
            ->groupBy('code_agency')
            ->orderByDesc('total')
            ->limit(25)
            ->get();

        $labels = [];
        $values = [];
        $backgroundColors = [];

        foreach ($topAgencies as $quoteData) {
            // Buscamos el nombre de la agencia
            $agencyName = Agency::where('code', $quoteData->code_agency)->first()?->name_corporative
                ?? "Agencia: {$quoteData->code_agency}";

            $labels[] = $agencyName;
            $values[] = $quoteData->total;
            $backgroundColors[] = $this->getRandomVibrantColor();
        }

        return [
            'datasets' => [
                [
                    'label' => "Total Cotizaciones ({$activeFilter})",
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
                    enabled: true,
                    backgroundColor: 'rgba(255, 255, 255, 0.98)',
                    titleColor: '#1d1d1f',
                    bodyColor: '#1d1d1f',
                    borderColor: '#d2d2d7',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: true,
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },
                        label: function(context) {
                            return ' Cotizaciones: ' + context.raw;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.15)' // Cuadrícula horizontal
                    },
                    ticks: { 
                        stepSize: 1,
                        color: '#86868b' 
                    }
                },
                x: {
                    grid: {
                        display: true, // Cuadrícula vertical activada
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.1)' // Cuadrícula vertical sutil
                    },
                    ticks: {
                        color: '#86868b',
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 45,
                        font: {
                            size: 10
                        },
                        callback: function(value) {
                            let label = this.getLabelForValue(value);
                            if (label.length > 12) {
                                return label.substring(0, 10) + '...';
                            }
                            return label;
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
