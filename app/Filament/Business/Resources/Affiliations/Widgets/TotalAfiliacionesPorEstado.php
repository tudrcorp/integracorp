<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Models\State;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

class TotalAfiliacionesPorEstado extends ChartWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliations::class;
    }

    protected ?string $heading = 'RESUMEN DE AFILIACIONES INDIVIDUALES POR UBICACIÓN';

    protected ?string $description = 'Visualización de Afiliaciones Corporativas con desglose por estados y ciudades. Haz clic en una barra para ver el detalle de cuantos afiliados exiten por ciudad.';

    protected ?string $maxHeight = '300px';

    /**
     * Filtro de Año seleccionado.
     */
    public ?string $filter = null;

    public ?int $selectedStateId = null;

    public function __construct()
    {
        // Inicializar con el año actual
        $this->filter = (string) now()->year;
    }

    /**
     * Define las opciones del selector de filtros (Últimos 5 años).
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

    public function handleChartClick(array $payload): void
    {
        if ($this->selectedStateId === null) {
            $state = State::where('definition', $payload['label'])->first();

            if ($state) {
                $this->selectedStateId = $state->id;

                Notification::make()
                    ->title("Detalle: {$state->definition}")
                    ->body("Mostrando ciudades con afiliaciones activas en el año {$this->filter}.")
                    ->info()
                    ->send();
            }
        } else {
            $this->selectedStateId = null;

            Notification::make()
                ->title('Vista Nacional')
                ->body("Regresando al resumen por estados del año {$this->filter}.")
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

        // Obtenemos el año del filtro
        $selectedYear = (int) ($this->filter ?? now()->year);

        if ($this->selectedStateId) {
            /**
             * VISTA POR CIUDAD (Drill-down)
             */
            $stateName = State::find($this->selectedStateId)?->definition ?? 'Estado';

            $stats = $this->getPageTableQuery()
                ->reorder()
                ->where('status', 'ACTIVA')
                ->whereYear('created_at', $selectedYear) // Aplicamos filtro de año
                ->where('state_id_ti', $this->selectedStateId)
                ->select('city_id_ti', DB::raw('count(*) as total'))
                ->groupBy('city_id_ti')
                ->get();

            $palette = $this->getBarColors();
            foreach ($stats as $index => $stat) {
                $cityName = DB::table('cities')->where('id', $stat->city_id_ti)->value('definition') ?? "Ciudad #{$stat->city_id_ti}";
                $labels[] = $cityName;
                $values[] = $stat->total;

                $backgroundColors[] = $palette[$index % count($palette)];
            }

            $datasetLabel = "Afiliaciones en {$stateName} ({$selectedYear})";
        } else {
            /**
             * VISTA POR ESTADO (General)
             */
            $stats = $this->getPageTableQuery()
                ->reorder()
                ->select('state_id_ti', DB::raw('count(*) as total'))
                ->where('status', 'ACTIVA')
                ->whereYear('created_at', $selectedYear) // Aplicamos filtro de año
                ->groupBy('state_id_ti')
                ->pluck('total', 'state_id_ti');

            $allStates = State::all(['id', 'definition']);

            $palette = $this->getBarColors();
            foreach ($allStates as $index => $state) {
                $labels[] = $state->definition;
                $values[] = $stats->get($state->id, 0);

                $backgroundColors[] = $palette[$index % count($palette)];
            }

            $datasetLabel = "Afiliaciones por Estado ({$selectedYear})";
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 6,
                    'barPercentage' => 0.8,
                    'categoryPercentage' => 1.0,
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
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { 
                        stepSize: 1,
                        color: '#86868b' 
                    },
                    grid: {
                        display: true,
                        color: 'rgba(156, 163, 175, 0.2)', // Color gris suave visible en light/dark
                        drawTicks: true,
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: true,
                        color: 'rgba(156, 163, 175, 0.1)', // Líneas verticales tenues adaptables
                        drawOnChartArea: true,
                        drawBorder: false
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
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(255, 255, 255, 0.98)',
                    titleColor: '#1d1d1f',
                    bodyColor: '#1d1d1f',
                    footerColor: '#86868b',
                    borderColor: '#d2d2d7',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },
                        footer: () => 'Clic para profundizar / regresar'
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
