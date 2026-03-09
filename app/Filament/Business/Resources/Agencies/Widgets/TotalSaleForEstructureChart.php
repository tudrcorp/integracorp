<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Models\Agency;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TotalSaleForEstructureChart extends ChartWidget
{
    protected ?string $heading = 'Total de ventas por agencia';

    protected ?string $description = 'Haz clic en las barras para ver el detalle de las ventas por agencia.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '500px';

    // Propiedades para el estado de navegación
    public ?string $selectedAgencyCode = null;

    public ?string $selectedAgencyName = null;

    // Propiedad para forzar el refresco reactivo
    public int $chartKey = 0;

    /**
     * Define los filtros de tiempo en la parte superior del widget
     */
    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hoy',
            'week' => 'Esta Semana',
            'month' => 'Este Mes',
            'last_month' => 'Mes Pasado',
            'year' => 'Este Año',
            'all' => 'Todo el tiempo',
        ];
    }

    /**
     * Filtro por defecto al cargar el widget
     */
    public ?string $filter = 'year';

    /**
     * Genera los datos del gráfico. Puede invocarse estáticamente con valores por defecto
     * o desde la instancia usando el estado del widget.
     *
     * @param  array{filter?: string, selectedAgencyCode?: string|null, selectedAgencyName?: string|null, chartKey?: int}  $params
     */
    public static function buildChartData(array $params = []): array
    {
        $activeFilter = $params['filter'] ?? 'year';
        $selectedAgencyCode = $params['selectedAgencyCode'] ?? null;
        $chartKey = $params['chartKey'] ?? 0;
        $year = (int) ($params['year'] ?? Carbon::now()->year);

        $querySales = function ($query) use ($activeFilter, $year) {
            if ($activeFilter === 'today') {
                $query->whereDate('sales.created_at', Carbon::today());
            } elseif ($activeFilter === 'week') {
                $query->whereBetween('sales.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            } elseif ($activeFilter === 'month') {
                $query->whereMonth('sales.created_at', Carbon::now()->month)
                    ->whereYear('sales.created_at', Carbon::now()->year);
            } elseif ($activeFilter === 'last_month') {
                $query->whereMonth('sales.created_at', Carbon::now()->subMonth()->month)
                    ->whereYear('sales.created_at', Carbon::now()->subMonth()->year);
            } elseif ($activeFilter === 'year') {
                $query->whereYear('sales.created_at', $year);
            }
        };

        if ($selectedAgencyCode) {
            $selectedAgencyName = $params['selectedAgencyName'] ?? '';
            $salesData = Sale::query()
                ->select([
                    'agents.name as label',
                    DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
                ])
                ->join('agents', 'agents.id', '=', 'sales.agent_id')
                ->where('agents.owner_code', $selectedAgencyCode)
                ->where($querySales)
                ->groupBy('agents.name')
                ->having('total', '>', 0)
                ->orderByDesc('total')
                ->get();

            $datasetLabel = "Ventas Agentes: {$selectedAgencyName}";
        } else {
            $salesData = Agency::query()
                ->select([
                    DB::raw("CONCAT(agencies.code, ' - ', COALESCE(agencies.name_corporative, 'Sin nombre')) as label"),
                    DB::raw('COALESCE(SUM(sales.total_amount), 0) as total'),
                ])
                ->leftJoin('sales', function ($join) use ($querySales) {
                    $join->on('sales.code_agency', '=', 'agencies.code');
                    $querySales($join);
                })
                ->groupBy('agencies.code', 'agencies.name_corporative')
                ->having('total', '>', 0)
                ->orderByDesc('total')
                ->get();

            $datasetLabel = 'Ventas Totales por Agencia (USD)';
        }

        $colors = [
            '#38bdf8',
            '#0ea5e9',
            '#0284c7',
            '#0369a1',
            '#075985',
            '#0c4a6e',
            '#7dd3fc',
            '#06b6d4',
            '#0891b2',
            '#0e7490',
        ];
        $dataCount = $salesData->count();
        $backgroundColors = [];
        for ($i = 0; $i < $dataCount; $i++) {
            $backgroundColors[] = $colors[$i % count($colors)];
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $salesData->pluck('total')->map(fn ($v) => (float) $v)->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => 'rgba(0,0,0,0.1)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $salesData->pluck('label')->toArray(),
            'key' => $chartKey,
        ];
    }

    protected function getData(): array
    {
        return self::buildChartData([
            'filter' => $this->filter,
            'selectedAgencyCode' => $this->selectedAgencyCode,
            'selectedAgencyName' => $this->selectedAgencyName,
            'chartKey' => $this->chartKey,
        ]);
    }

    public function handleChartClick(array $payload): void
    {
        $label = $payload['label'] ?? null;
        if (! $label) {
            return;
        }

        if ($this->selectedAgencyCode === null) {
            $code = trim(explode(' - ', $label, 2)[0]);
            $agency = Agency::where('code', $code)->first();

            if ($agency) {
                $this->selectedAgencyCode = $agency->code;
                $this->selectedAgencyName = $agency->name_corporative;
                $this->heading = "Desglose: {$agency->name_corporative}";
                $this->dispatch('chartAgencySelected', agencyCode: $agency->code);
            }
        } else {
            $this->selectedAgencyCode = null;
            $this->selectedAgencyName = null;
            $this->heading = 'Total de ventas por agencia';
            $this->dispatch('chartAgencyCleared');
        }

        $this->chartKey++;
        $this->updateChartData();
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            onClick: (event, elements, chart) => {
                if (elements && elements.length > 0) {
                    const index = elements[0].index;
                    const label = chart.data.labels[index];
                    $wire.handleChartClick({ label: label, index: index });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            barPercentage: 0.6,
            categoryPercentage: 0.8,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        footer: () => 'Clic para profundizar o regresar'
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: { callback: (value) => '$' + value.toLocaleString() },
                    grid: { color: 'rgba(156, 163, 175, 0.2)', drawBorder: false }
                },
                x: { grid: { display: true } }
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
