<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Models\State;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

class AffiliationCorporatePorEstadoChart extends ChartWidget
{

    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliationCorporates::class;
    }

    protected ?string $heading = 'RESUMEN DE AFILIACIONES CORPORATIVAS POR UBICACIÓN';

    protected ?string $description = 'Visualización de Afiliaciones Corporativas con desglose por estados y ciudades. Al hacer click en cualquiera de las barras podras observar el detalle de las afiliaciones de acuerdo al estado seleccionado';

    protected ?string $maxHeight = '300px';

    /**
     * Estado para controlar el Drill-down
     * null: Muestra Estados
     * int: ID del estado seleccionado para mostrar Ciudades
     */
    public ?int $selectedStateId = null;

    /**
     * Maneja el clic desde el frontend
     */
    public function handleChartClick(array $payload): void
    {
        if ($this->selectedStateId === null) {
            // Buscamos el ID del estado basado en el nombre (label) que viene del gráfico
            $state = State::where('definition', $payload['label'])->first();

            if ($state) {
                $this->selectedStateId = $state->id;

                Notification::make()
                    ->title("Detalle: {$state->definition}")
                    ->body("Mostrando afiliaciones activas por ciudad.")
                    ->info()
                    ->send();
            }
        } else {
            // Si ya estábamos en una ciudad, regresamos a la vista de estados
            $this->selectedStateId = null;

            Notification::make()
                ->title("Vista Nacional")
                ->body("Regresando al resumen por estados.")
                ->success()
                ->send();
        }
    }

    protected function getData(): array
    {
        $labels = [];
        $values = [];
        $datasetLabel = '';
        $backgroundColor = '';
        $year = now()->year;

        if ($this->selectedStateId) {
            /**
             * VISTA POR CIUDAD (Drill-down)
             */
            $stateName = State::find($this->selectedStateId)?->definition ?? 'Estado';

            /**
             * VISTA MENSUAL: Conteo de Afiliados (Relación 1 a N)
             * Queremos saber cuántos registros hay en la tabla 'affiliates' 
             * pertenecientes a las afiliaciones activas de este mes.
             */
            $startOfMonth   = Carbon::create($year, $this->selectedMonth)->startOfMonth();
            $endOfMonth     = Carbon::create($year, $this->selectedMonth)->endOfMonth();

            // Agrupamos por la columna de ciudad (asumiendo city_id_ti)
            $stats = $this->getPageTableQuery()
                ->reorder()
                ->where('status', 'ACTIVA')
                ->where('state_id', $this->selectedStateId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->select('city_id', DB::raw('count(*) as total'))
                ->groupBy('city_id')
                ->get();

            // Mapeamos nombres de ciudades (asumiendo relación o tabla cities)
            foreach ($stats as $stat) {
                // Intenta obtener el nombre de la ciudad. Ajusta según tu lógica de nombres.
                $cityName = DB::table('cities')->where('id', $stat->city_id)->value('definition') ?? "Ciudad #{$stat->city_id}";
                $labels[] = $cityName;
                $values[] = $stat->total;
            }

            $datasetLabel = "Afiliaciones en {$stateName} (por ciudad)";
            $backgroundColor = '#10b981'; // Verde para ciudades
        } else {
            /**
             * VISTA POR ESTADO (General)
             */

            // $startOfMonth = Carbon::create($year, $this->selectedMonth)->startOfMonth();
            // $endOfMonth = Carbon::create($year, $this->selectedMonth)->endOfMonth();

            $stats = $this->getPageTableQuery()
                ->reorder()
                ->select('state_id', DB::raw('count(*) as total'))
                ->where('status', 'ACTIVA')
                // ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->groupBy('state_id')
                ->pluck('total', 'state_id');

            $allStates = State::all(['id', 'definition']);

            foreach ($allStates as $state) {
                $labels[] = $state->definition;
                $values[] = $stats->get($state->id, 0);
            }

            $datasetLabel = 'Afiliaciones por Estado';
            $backgroundColor = $this->getChartColors();
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $values,
                    'backgroundColor' => $backgroundColor,
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $labels,
        ];
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

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            onClick: (event, elements, chart) => {
                if (elements && elements.length > 0) {
                    const activeElement = elements[0];
                    const dataIndex = activeElement.index;
                    const label = chart.data.labels[dataIndex];

                    // Llamamos al método de Livewire para profundizar o regresar
                    $wire.handleChartClick({
                        label: label,
                        indice: dataIndex
                    });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        footer: () => 'Clic para ver ciudades / regresar'
                    }
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
