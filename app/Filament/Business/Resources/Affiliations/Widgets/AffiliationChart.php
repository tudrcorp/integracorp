<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliate;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class AffiliationChart extends ChartWidget
{
    protected ?string $heading = 'RESUMEN DE AFILIACIONES Y AFILIADOS INDIVIDUALES';

    protected ?string $description = 'Visualización mensual de afiliaciones con desglose por días del mes. Haz clic en las barras para observar el detalle o usa el botón para resetear.';

    protected ?string $maxHeight = '300px';

    /**
     * Filtro de Año (Últimos 5 años)
     */
    public ?string $filter = null;

    /**
     * Estado para controlar el drill-down mensual.
     */
    public ?int $selectedMonth = null;

    public function __construct()
    {
        $this->filter = (string) now()->year;
    }

    /**
     * Define las opciones del selector de filtros.
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
     * Acción de encabezado para resetear el gráfico.
     * Solo es visible cuando hay un mes seleccionado.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Volver a Vista Anual')
                ->color('gray')
                ->size('sm')
                ->icon('heroicon-m-arrow-path')
                ->visible(fn () => $this->selectedMonth !== null)
                ->action(function () {
                    $this->selectedMonth = null;

                    Notification::make()
                        ->title('Gráfico Reseteado')
                        ->body('Regresando al resumen anual.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Maneja el clic en las barras para alternar vistas.
     */
    public function handleChartClick(array $payload): void
    {
        if ($this->selectedMonth === null) {
            $this->selectedMonth = $payload['indice'] + 1;

            Notification::make()
                ->title("Detalle de Afiliados: {$payload['mes']} {$this->filter}")
                ->body('Mostrando el total de personas afiliadas por día.')
                ->info()
                ->send();

        } else {
            // Si el usuario hace clic de nuevo en una barra del detalle, también resetea
            $this->selectedMonth = null;
        }
    }

    protected function getData(): array
    {
        $selectedYear = (int) ($this->filter ?? now()->year);
        $backgroundColors = [];

        if ($this->selectedMonth) {
            /**
             * VISTA MENSUAL: Detalle por día
             */
            $startOfMonth = Carbon::create($selectedYear, $this->selectedMonth)->startOfMonth();
            $endOfMonth = Carbon::create($selectedYear, $this->selectedMonth)->endOfMonth();

            $dataTrend = Trend::query(
                Affiliate::query()
                    ->whereHas('affiliation', function ($query) {
                        $query->where('status', 'ACTIVA');
                    })
            )
                ->between(start: $startOfMonth, end: $endOfMonth)
                ->perDay()
                ->count();

            $labels = $dataTrend->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('d'))->toArray();
            $monthName = Carbon::create(null, $this->selectedMonth)->monthName;
            $datasetLabel = "Afiliados en {$monthName} {$selectedYear}";

            $palette = $this->getBarColors();
            foreach ($labels as $index => $label) {
                $backgroundColors[] = $palette[$index % count($palette)];
            }
        } else {
            /**
             * VISTA ANUAL: Resumen por mes
             */
            $startOfYear = Carbon::create($selectedYear)->startOfYear();
            $endOfYear = Carbon::create($selectedYear)->endOfYear();

            $dataTrend = Trend::query(
                Affiliation::query()
                    ->where('status', 'ACTIVA')
                    ->whereYear('created_at', $selectedYear)
            )
                ->between(start: $startOfYear, end: $endOfYear)
                ->perMonth()
                ->count();

            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $datasetLabel = "Afiliaciones Activas ({$selectedYear})";

            $palette = $this->getBarColors();
            foreach ($labels as $index => $label) {
                $backgroundColors[] = $palette[$index % count($palette)];
            }
        }

        $dataValues = $dataTrend->map(fn (TrendValue $value) => (int) $value->aggregate)->toArray();

        // Si no hay valores en el mes seleccionado, mostramos una notificación automática
        if ($this->selectedMonth && array_sum($dataValues) === 0) {
            Notification::make()
                ->title('Sin datos')
                ->body('No se encontraron afiliaciones para el mes seleccionado.')
                ->warning()
                ->send();
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $dataValues,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 6,
                    'barPercentage' => 0.8,
                    'categoryPercentage' => 0.9,
                ],
            ],
            'labels' => $labels,
        ];
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

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            onClick: (event, elements, chart) => {
                if (elements && elements.length > 0) {
                    const activeElement = elements[0];
                    const dataIndex = activeElement.index;
                    const label = chart.data.labels[dataIndex];
                    console.log(label);
                    console.log(dataIndex);
                    console.log(chart);

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
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 1)',
                    titleColor: '#000000',
                    bodyColor: '#000000',
                    footerColor: '#000000',
                    borderColor: '#d2d2d7',
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        footer: () => 'Haz clic para detallar o volver'
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 1 },
                    grid: {
                        display: true,
                        color: 'rgba(156, 163, 175, 0.2)', // Gris suave adaptable
                        drawBorder: false
                    }
                },
                x: { 
                    grid: { 
                        display: true,
                        color: 'rgba(156, 163, 175, 0.1)', // Líneas verticales más tenues
                        drawBorder: false
                    } 
                }
            }
        }
        JS);
    }

    protected function getTablePage(): string
    {
        // Placeholder en caso de que este widget interactúe con una tabla
        return '';
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
