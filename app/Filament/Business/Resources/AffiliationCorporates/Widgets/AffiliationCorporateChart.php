<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Models\AffiliationCorporate;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class AffiliationCorporateChart extends ChartWidget
{
    protected ?string $heading = 'RESUMEN DE AFILIACIONES Y AFILIADOS CORPORATIVOS';

    protected ?string $description = 'Visualización mensual de afiliaciones con desglose por dias del Mes. Al hacer click en cualquiera de las barras podras observar el detalle de las afiliaciones de acuerdo al mes seleccionado';


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

    protected function getData(): array
    {
        $year = now()->year;

        if ($this->selectedMonth) {
            /**
             * VISTA MENSUAL: Conteo de Afiliados (Relación 1 a N)
             * Queremos saber cuántos registros hay en la tabla 'affiliates' 
             * pertenecientes a las afiliaciones activas de este mes.
             */
            $startOfMonth = Carbon::create($year, $this->selectedMonth)->startOfMonth();
            $endOfMonth = Carbon::create($year, $this->selectedMonth)->endOfMonth();

            $data = Trend::query(
                \App\Models\AffiliateCorporate::query()
                    ->whereHas('affiliationCorporate', function ($query) {
                        $query->where('status', 'ACTIVA');
                    })
            )
                ->between(start: $startOfMonth, end: $endOfMonth)
                ->perDay()
                ->count();

            $labels = $data->map(fn(TrendValue $value) => Carbon::parse($value->date)->format('d'))->toArray();
            $datasetLabel = 'Total Afiliados en ' . Carbon::create(null, $this->selectedMonth)->monthName;
            $color = '#f59e0b'; // Ámbar para diferenciar la métrica de "Afiliados"
        } else {
            /**
             * VISTA ANUAL: Conteo de registros en la tabla Affiliation
             */
            $data = Trend::query(
                AffiliationCorporate::query()->where('status', 'ACTIVA')->whereYear('created_at', $year)
            )
                ->between(start: now()->startOfYear(), end: now()->endOfYear())
                ->perMonth()
                ->count();

            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $datasetLabel = 'Afiliaciones Activas (Anual)';
            $color = $this->getChartColors(); // Azul para "Afiliaciones"
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $data->map(fn(TrendValue $value) => (int) $value->aggregate)->toArray(),
                    'backgroundColor' => $color,
                    'borderRadius' => 6,
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
                    display: true,
                    position: 'top'
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
