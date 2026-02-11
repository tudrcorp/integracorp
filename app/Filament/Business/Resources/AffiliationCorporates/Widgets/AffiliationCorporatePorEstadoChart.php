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

    protected ?string $description = 'Visualización por estados y ciudades filtrada por año. Haz clic en las barras para ver el detalle de ciudades.';

    protected ?string $maxHeight = '300px';

    /**
     * Estado para controlar el Drill-down
     */
    public ?int $selectedStateId = null;

    /**
     * Define el filtro de años (Select de los últimos 5 años)
     */
    protected function getFilters(): ?array
    {
        $currentYear = now()->year;
        $years = [];

        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;
            $years[$year] = (string) $year;
        }

        return $years;
    }

    /**
     * Maneja el clic desde el frontend
     */
    public function handleChartClick(array $payload): void
    {
        $year = (int) ($this->filter ?? now()->year);

        if ($this->selectedStateId === null) {
            $state = State::where('definition', $payload['label'])->first();

            if ($state) {
                $this->selectedStateId = $state->id;

                Notification::make()
                    ->title("Detalle: {$state->definition} ({$year})")
                    ->body("Mostrando afiliaciones activas por ciudad.")
                    ->info()
                    ->send();
            }
        } else {
            $this->selectedStateId = null;

            Notification::make()
                ->title("Vista Nacional {$year}")
                ->body("Regresando al resumen por estados.")
                ->success()
                ->send();
        }
    }

    protected function getData(): array
    {
        $labels = [];
        $values = [];
        $backgroundColors = [];
        $datasetLabel = '';

        /**
         * Obtenemos el año desde el filtro del widget
         */
        $year = (int) ($this->filter ?? now()->year);

        if ($this->selectedStateId) {
            /**
             * VISTA POR CIUDAD (Drill-down)
             */
            $stateName = State::find($this->selectedStateId)?->definition ?? 'Estado';

            $stats = $this->getPageTableQuery()
                ->reorder()
                ->where('status', 'ACTIVA')
                ->where('state_id', $this->selectedStateId)
                ->whereYear('created_at', $year)
                ->select('city_id', DB::raw('count(*) as total'))
                ->groupBy('city_id')
                ->get();

            foreach ($stats as $stat) {
                $cityName = DB::table('cities')->where('id', $stat->city_id)->value('definition') ?? "Ciudad #{$stat->city_id}";
                $labels[] = $cityName;
                $values[] = $stat->total;
                $backgroundColors[] = sprintf('#%06X', mt_rand(0x444444, 0xAAAAAA));
            }

            $datasetLabel = "Afiliaciones en {$stateName} - {$year}";
        } else {
            /**
             * VISTA POR ESTADO (General)
             */
            $stats = $this->getPageTableQuery()
                ->reorder()
                ->where('status', 'ACTIVA')
                ->whereYear('created_at', $year)
                ->select('state_id', DB::raw('count(*) as total'))
                ->groupBy('state_id')
                ->pluck('total', 'state_id');

            $allStates = State::all(['id', 'definition']);

            foreach ($allStates as $state) {
                $labels[] = $state->definition;
                $values[] = $stats->get($state->id, 0);
                $backgroundColors[] = sprintf('#%06X', mt_rand(0x10b981, 0x3b82f6)); // Tonos entre verde y azul
            }

            $datasetLabel = "Afiliaciones por Estado ({$year})";
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 6,
                    'barPercentage' => 0.8,
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
                        label: label,
                        indice: dataIndex
                    });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#111827',
                    bodyColor: '#374151',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        footer: () => 'Haz clic para ver ciudades / regresar'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.15)', // Cuadrícula horizontal
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: { size: 10 },
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.1)', // Cuadrícula vertical
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: { size: 10 }
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
