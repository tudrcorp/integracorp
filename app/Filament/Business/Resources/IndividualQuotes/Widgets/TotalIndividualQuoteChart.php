<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Models\IndividualQuote;
use App\Models\State;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Facades\DB;

class TotalIndividualQuoteChart extends ChartWidget
{
    protected ?string $heading = 'RESUMEN DE COTIZACIONES INDIVIDUALES';

    protected ?string $description = 'Visualización mensual de cotizaciones con desglose por días del mes. Haz clic en las barras para observar el detalle de las cotizaciones por dia de acuerdo al mes seleccionado.';

    protected ?string $maxHeight = '300px';

    /**
     * Estado para controlar los filtros.
     */
    public ?int $selectedMonth = null;

    public ?string $filter = null;

    /**
     * Definición de los filtros (Últimos 5 años)
     */
    protected function getFilters(): ?array
    {
        $years = [];
        $currentYear = now()->year;

        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;
            $years[$year] = (string) $year;
        }

        return $years;
    }

    /**
     * Maneja el clic en las barras. 
     */
    public function handleChartClick(array $payload): void
    {
        if ($this->selectedMonth === null) {
            $this->selectedMonth = $payload['indice'] + 1;

            Notification::make()
                ->title("Detalle de Cotizaciones: {$payload['mes']}")
                ->body("Mostrando el total de cotizaciones en este periodo.")
                ->info()
                ->send();
        } else {
            $this->selectedMonth = null;

            Notification::make()
                ->title('Vista Anual')
                ->body('Regresando al resumen anual de afiliaciones.')
                ->success()
                ->send();
        }
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);
        $backgroundColors = [];

        if ($this->selectedMonth) {
            /**
             * VISTA MENSUAL: Conteo de Afiliados por día
             */
            $startOfMonth = Carbon::create($year, $this->selectedMonth)->startOfMonth();
            $endOfMonth = Carbon::create($year, $this->selectedMonth)->endOfMonth();

            $dataTrend = Trend::query(
                \App\Models\IndividualQuote::query()
            )
                ->between(start: $startOfMonth, end: $endOfMonth)
                ->perDay()
                ->count();

            $labels = $dataTrend->map(fn(TrendValue $value) => Carbon::parse($value->date)->format('d'))->toArray();
            $datasetLabel = 'Total Cotizaciones en ' . Carbon::create(null, $this->selectedMonth)->monthName . " ($year)";

            // Generar colores suaves estilo pastel
            foreach ($labels as $label) {
                $backgroundColors[] = sprintf('#%06X', mt_rand(0x606060, 0xCCCCCC));
            }
        } else {
            /**
             * VISTA ANUAL: Conteo por Mes
             */
            $dataTrend = Trend::query(
                IndividualQuote::query()->whereYear('created_at', $year)
            )
                ->between(
                    start: Carbon::create($year)->startOfYear(),
                    end: Carbon::create($year)->endOfYear()
                )
                ->perMonth()
                ->count();

            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $datasetLabel = "Cotizaciones Activas ($year)";

            // Generar colores aleatorios para cada mes
            foreach ($labels as $label) {
                $backgroundColors[] = sprintf('#%06X', mt_rand(0x404040, 0xDDDDDD));
            }
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $dataTrend->map(fn(TrendValue $value) => (int) $value->aggregate)->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 6,
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
            onClick: (event, elements, chart) => {
                if (elements && elements.length > 0) {
                    const activeElement = elements[0];
                    const dataIndex = activeElement.index;
                    const label = chart.data.labels[dataIndex];

                    $wire.handleChartClick({
                        mes: label,
                        indice: dataIndex
                    });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.98)',
                    titleColor: '#1d1d1f',
                    bodyColor: '#1d1d1f',
                    borderColor: '#d2d2d7',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        footer: () => 'Haz clic para alternar vista'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.15)'
                    },
                    ticks: { 
                        stepSize: 1,
                        color: '#86868b' 
                    }
                },
                x: {
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.1)'
                    },
                    ticks: {
                        color: '#86868b'
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
