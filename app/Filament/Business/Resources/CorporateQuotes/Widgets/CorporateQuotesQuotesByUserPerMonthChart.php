<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Widgets;

use App\Models\CorporateQuote;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CorporateQuotesQuotesByUserPerMonthChart extends ChartWidget
{
    protected string $view = 'filament.widgets.corporate-quotes-quotes-by-user-per-month-chart';

    protected ?string $heading = 'COTIZACIONES CORPORATIVAS POR USUARIO POR MES';

    protected ?string $description = 'Cantidad de cotizaciones agrupadas por usuario (created_by) y mes. Solo se incluyen usuarios con más de 1 cotización en el año seleccionado.';

    protected ?string $maxHeight = '440px';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<int, Collection<int, object{created_by: mixed, total: int|string}>>
     */
    protected array $topUsersAnnualTotalsMemo = [];

    protected function getFilters(): ?array
    {
        $year = now()->year;
        $filters = [];

        for ($i = 0; $i < 5; $i++) {
            $yearValue = $year - $i;
            $filters[$yearValue] = (string) $yearValue;
        }

        return $filters;
    }

    /**
     * Paleta fija de colores para las barras (mismo orden = mismo color).
     */
    protected function getBarColors(): array
    {
        return [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
            '#06B6D4', '#EC4899', '#84CC16', '#F97316', '#6366F1',
        ];
    }

    /**
     * Usuarios incluidos en el gráfico (más de 1 cotización en el año) con total anual.
     *
     * @return Collection<int, object{created_by: mixed, total: int|string}>
     */
    protected function getTopUsersWithAnnualTotals(int $year): Collection
    {
        if (! array_key_exists($year, $this->topUsersAnnualTotalsMemo)) {
            $this->topUsersAnnualTotalsMemo[$year] = CorporateQuote::query()
                ->select('created_by', DB::raw('count(*) as total'))
                ->whereNotNull('created_by')
                ->where('created_by', '!=', '')
                ->whereYear('created_at', $year)
                ->groupBy('created_by')
                ->having('total', '>', 1)
                ->orderByDesc('total')
                ->limit(10)
                ->get();
        }

        return $this->topUsersAnnualTotalsMemo[$year];
    }

    /**
     * Leyenda para mostrar junto al filtro de año: usuario + total anual (mismo orden y color que las barras).
     *
     * @return list<array{label: string, total: int, color: string}>
     */
    public function getFilterLegendItems(): array
    {
        $year = (int) ($this->filter ?? now()->year);
        $palette = $this->getBarColors();
        $items = [];
        foreach ($this->getTopUsersWithAnnualTotals($year) as $index => $row) {
            $items[] = [
                'label' => (string) $row->created_by,
                'total' => (int) $row->total,
                'color' => $palette[$index % count($palette)],
            ];
        }

        return $items;
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        $topUsers = $this->getTopUsersWithAnnualTotals($year)->pluck('created_by')->values();

        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        if ($topUsers->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => "Cotizaciones ($year)",
                        'data' => array_fill(0, 12, 0),
                        'backgroundColor' => 'rgba(148, 163, 184, 0.35)',
                        'borderRadius' => 8,
                        'barPercentage' => 0.8,
                        'categoryPercentage' => 0.9,
                    ],
                ],
                'labels' => $labels,
            ];
        }

        $rows = CorporateQuote::query()
            ->select([
                'created_by',
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total'),
            ])
            ->whereYear('created_at', $year)
            ->whereIn('created_by', $topUsers)
            ->groupBy('created_by', DB::raw('MONTH(created_at)'))
            ->get();

        $matrix = [];
        foreach ($topUsers as $userName) {
            $matrix[$userName] = array_fill(1, 12, 0);
        }

        foreach ($rows as $row) {
            $user = (string) $row->created_by;
            $month = (int) $row->month;
            $matrix[$user][$month] = (int) $row->total;
        }

        $palette = $this->getBarColors();
        $datasets = [];
        foreach ($topUsers as $index => $userName) {
            $color = $palette[$index % count($palette)];
            $datasets[] = [
                'label' => $userName,
                'data' => array_values($matrix[$userName]),
                'backgroundColor' => $color,
                'borderRadius' => 8,
                'barPercentage' => 0.8,
                'categoryPercentage' => 0.9,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(255, 255, 255, 0.98)',
                    titleColor: '#1d1d1f',
                    bodyColor: '#1d1d1f',
                    borderColor: '#d2d2d7',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },
                        label: function(context) {
                            return ' ' + context.dataset.label + ': ' + context.raw;
                        }
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
                        precision: 0,
                        stepSize: 1,
                        color: '#86868b',
                        callback: (value) => Number(value).toLocaleString()
                    }
                },
                x: {
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.1)'
                    },
                    ticks: {
                        color: '#86868b',
                        autoSkip: false,
                        maxRotation: 0,
                        minRotation: 0,
                        font: {
                            size: 11
                        }
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
