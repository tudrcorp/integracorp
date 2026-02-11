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

    protected ?string $description = 'Visualización mensual de afiliaciones con desglose por días del Mes. Haz clic en una barra para ver el detalle de afiliados.';

    protected ?string $maxHeight = '300px';

    /**
     * Estado para controlar el filtro de mes.
     */
    public ?int $selectedMonth = null;

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

    public function handleChartClick(array $payload): void
    {
        $year = (int) ($this->filter ?? now()->year);

        if ($this->selectedMonth === null) {
            $this->selectedMonth = $payload['indice'] + 1;

            Notification::make()
                ->title("Detalle de Afiliados: {$payload['mes']} {$year}")
                ->body("Mostrando el desglose diario de personas afiliadas.")
                ->info()
                ->send();
        } else {
            $this->selectedMonth = null;

            Notification::make()
                ->title("Vista Anual {$year}")
                ->body('Regresando al resumen de afiliaciones por mes.')
                ->success()
                ->send();
        }
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);
        $backgroundColors = [];

        if ($this->selectedMonth) {
            $startOfMonth = Carbon::create($year, $this->selectedMonth)->startOfMonth();
            $endOfMonth = Carbon::create($year, $this->selectedMonth)->endOfMonth();

            $data = Trend::query(
                \App\Models\AffiliateCorporate::query()
                    ->whereHas('affiliationCorporate', function ($query) use ($year) {
                        $query->where('status', 'ACTIVA')
                            ->whereYear('created_at', $year);
                    })
            )
                ->between(start: $startOfMonth, end: $endOfMonth)
                ->perDay()
                ->count();

            $labels = $data->map(fn(TrendValue $value) => Carbon::parse($value->date)->format('d'))->toArray();
            $datasetLabel = 'Afiliados en ' . Carbon::create($year, $this->selectedMonth)->translatedMonth . " ({$year})";
        } else {
            $startOfYear = Carbon::create($year)->startOfYear();
            $endOfYear = Carbon::create($year)->endOfYear();

            $data = Trend::query(
                AffiliationCorporate::query()->where('status', 'ACTIVA')->whereYear('created_at', $year)
            )
                ->between(start: $startOfYear, end: $endOfYear)
                ->perMonth()
                ->count();

            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $datasetLabel = "Afiliaciones Activas (Anual {$year})";
        }

        // Generar colores consistentes pero variados
        foreach ($labels as $label) {
            $backgroundColors[] = sprintf('#%06X', mt_rand(0x444444, 0xAAAAAA));
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $data->map(fn(TrendValue $value) => (int) $value->aggregate)->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 4,
                    'barPercentage' => 0.7,
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
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#111827',
                    bodyColor: '#374151',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 10,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.15)', // Líneas horizontales
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: { size: 10 }
                    }
                },
                x: {
                    grid: {
                        display: true, // Activamos líneas verticales
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.1)', // Líneas verticales más sutiles
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
