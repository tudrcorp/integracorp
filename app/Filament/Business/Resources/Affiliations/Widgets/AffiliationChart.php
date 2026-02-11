<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AffiliationChart extends ChartWidget
{
    protected ?string $heading = 'RESUMEN DE AFILIACIONES Y AFILIADOS INDIVIDUALES';

    protected ?string $description = 'Visualización mensual de afiliaciones con desglose por días del mes. Haz clic en las barras para observar el detalle de las afiliaciones por dia de acuerdo al mes seleccionado.';

    protected ?string $maxHeight = '300px';

    /**
     * Estado para controlar el filtro.
     * null: Muestra resumen anual de afiliaciones.
     * 1-12: Muestra total de afiliados por día en el mes seleccionado.
     */
    public ?int $selectedMonth = null;

    /**
     * Maneja el clic en las barras. 
     */
    public function handleChartClick(array $payload): void
    {
        if ($this->selectedMonth === null) {
            $this->selectedMonth = $payload['indice'] + 1;

            Notification::make()
                ->title("Detalle de Afiliados: {$payload['mes']}")
                ->body("Mostrando el total de personas afiliadas en este periodo.")
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
        $year = now()->year;
        $backgroundColors = [];

        if ($this->selectedMonth) {
            /**
             * VISTA MENSUAL: Conteo de Afiliados por día
             */
            $startOfMonth = Carbon::create($year, $this->selectedMonth)->startOfMonth();
            $endOfMonth = Carbon::create($year, $this->selectedMonth)->endOfMonth();

            $dataTrend = Trend::query(
                \App\Models\Affiliate::query()
                    ->whereHas('affiliation', function ($query) {
                        $query->where('status', 'ACTIVA');
                    })
            )
                ->between(start: $startOfMonth, end: $endOfMonth)
                ->perDay()
                ->count();

            $labels = $dataTrend->map(fn(TrendValue $value) => Carbon::parse($value->date)->format('d'))->toArray();
            $datasetLabel = 'Total Afiliados en ' . Carbon::create(null, $this->selectedMonth)->monthName;

            // Generar colores aleatorios para cada día
            foreach ($labels as $label) {
                $backgroundColors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            }
        } else {
            /**
             * VISTA ANUAL: Conteo por Mes
             */
            $dataTrend = Trend::query(
                Affiliation::query()->where('status', 'ACTIVA')->whereYear('created_at', $year)
            )
                ->between(start: now()->startOfYear(), end: now()->endOfYear())
                ->perMonth()
                ->count();

            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $datasetLabel = 'Afiliaciones Activas (Anual)';

            // Generar colores aleatorios para cada mes
            foreach ($labels as $label) {
                $backgroundColors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            }
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $dataTrend->map(fn(TrendValue $value) => (int) $value->aggregate)->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 6,
                    /**
                     * Configuración de ancho de barras:
                     * Consistente con el gráfico de estados (más anchas).
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
                    display: false // Ocultamos leyenda para dar más espacio a las barras anchas
                },
                tooltip: {
                    callbacks: {
                        footer: () => 'Haz clic para alternar entre Afiliaciones y Afiliados'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
