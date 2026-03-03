<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Models\IndividualQuote;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

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
     * Paleta fija de colores para las barras (mismo orden = mismo color).
     */
    protected function getBarColors(): array
    {
        return [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
            '#06B6D4', '#EC4899', '#84CC16', '#F97316', '#6366F1',
            '#14B8A6', '#A855F7', '#EAB308', '#DC2626', '#2563EB',
            '#059669', '#D97706', '#BE185D', '#7C3AED', '#0D9488',
            '#65A30D', '#EA580C', '#4F46E5', '#0891B2', '#DB2777',
            '#0EA5E9', '#22C55E', '#E11D48', '#9333EA', '#F43F5E', '#64748B',
        ];
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
                ->body('Mostrando el total de cotizaciones en este periodo.')
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

            $labels = $dataTrend->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('d'))->toArray();
            $datasetLabel = 'Total Cotizaciones en '.Carbon::create(null, $this->selectedMonth)->monthName." ($year)";

            $palette = $this->getBarColors();
            foreach ($labels as $index => $label) {
                $backgroundColors[] = $palette[$index % count($palette)];
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

            $palette = $this->getBarColors();
            foreach ($labels as $index => $label) {
                $backgroundColors[] = $palette[$index % count($palette)];
            }
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $dataTrend->map(fn (TrendValue $value) => (int) $value->aggregate)->toArray(),
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
