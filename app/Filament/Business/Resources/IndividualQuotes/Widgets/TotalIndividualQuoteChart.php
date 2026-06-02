<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\IndividualQuote;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Facades\DB;

class TotalIndividualQuoteChart extends ChartWidget
{
    protected string $view = 'filament.widgets.total-individual-quote-chart';

    protected string $color = 'gray';

    protected ?string $heading = 'HISTORIAL DE COTIZACIONES MENSUALES';

    protected ?string $description = 'Histórico mensual de cotizaciones. Haz clic en un mes para ver el Top 15 de agentes con más cotizaciones en ese periodo.';

    protected ?string $maxHeight = '480px';

    protected int|string|array $columnSpan = 'full';

    /**
     * Estado para controlar los filtros.
     */
    public ?int $selectedMonth = null;

    public ?string $filter = null;

    /**
     * Vista del detalle mensual: agentes o agencias.
     */
    public string $detailView = 'agents';

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
     * Paleta de azules (misma que TotalSaleForEstructureChart).
     *
     * @return array<int, string>
     */
    protected function getBarColors(): array
    {
        return [
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
    }

    /**
     * @return array<int, string>
     */
    protected function buildBackgroundColors(int $count): array
    {
        $colors = $this->getBarColors();
        $backgroundColors = [];

        for ($i = 0; $i < $count; $i++) {
            $backgroundColors[] = $colors[$i % count($colors)];
        }

        return $backgroundColors;
    }

    /**
     * @param  array<int, int|float>  $data
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function makeBarDataset(string $label, array $data, array $extra = []): array
    {
        return array_merge([
            'label' => $label,
            'data' => $data,
            'backgroundColor' => $this->buildBackgroundColors(count($data)),
            'borderColor' => 'rgba(0,0,0,0.1)',
            'borderWidth' => 1.25,
            'borderRadius' => 8,
            'borderSkipped' => false,
        ], $extra);
    }

    /**
     * Abre el detalle del mes (Top 15 agentes).
     */
    public function openMonthDetail(int $month): void
    {
        if ($month < 1 || $month > 12) {
            return;
        }

        $this->selectedMonth = $month;
        $this->detailView = 'agents';
        $this->updateChartData();
    }

    /**
     * Alterna entre Top 15 agentes y Top 15 agencias.
     */
    public function toggleDetailView(): void
    {
        if ($this->selectedMonth === null) {
            return;
        }

        $this->detailView = $this->detailView === 'agents' ? 'agencies' : 'agents';
        $this->updateChartData();
    }

    /**
     * Regresa al histórico mensual.
     */
    public function resetToMonthly(): void
    {
        $this->selectedMonth = null;
        $this->detailView = 'agents';
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        if ($this->selectedMonth) {
            $month = (int) $this->selectedMonth;
            $monthName = Carbon::create(null, $month)->monthName;

            if ($this->detailView === 'agencies') {
                return $this->buildTopAgenciesDetailChart($year, $month, $monthName);
            }

            return $this->buildTopAgentsDetailChart($year, $month, $monthName);
        }

        /**
         * VISTA PRINCIPAL: Conteo por mes (histórico mensual).
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
        $values = $dataTrend->map(fn (TrendValue $value) => (int) $value->aggregate)->toArray();

        return [
            'datasets' => [
                $this->makeBarDataset("Cotizaciones ({$year})", $values),
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTopAgentsDetailChart(int $year, int $month, string $monthName): array
    {
        $topAgents = IndividualQuote::query()
            ->select([
                'agent_id',
                DB::raw('count(*) as total'),
                DB::raw('MAX(created_at) as last_quote_at'),
            ])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('agent_id')
            ->orderByDesc('total')
            ->orderByDesc('last_quote_at')
            ->limit(15)
            ->get();

        $labels = [];
        $values = [];
        $names = [];

        foreach ($topAgents as $row) {
            $agentId = (int) $row->agent_id;
            $agentName = Agent::find($agentId)?->name ?? "Agente #{$agentId}";

            $labels[] = $agentName;
            $names[] = $agentName;
            $values[] = (int) $row->total;
        }

        return [
            'datasets' => [
                $this->makeBarDataset(
                    "Top 15 agentes - {$monthName} ({$year})",
                    $values,
                    ['names' => $names],
                ),
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTopAgenciesDetailChart(int $year, int $month, string $monthName): array
    {
        $topAgencies = IndividualQuote::query()
            ->select([
                'code_agency',
                DB::raw('count(*) as total'),
                DB::raw('MAX(created_at) as last_quote_at'),
            ])
            ->whereNotNull('code_agency')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('code_agency')
            ->orderByDesc('total')
            ->orderByDesc('last_quote_at')
            ->limit(15)
            ->get();

        $labels = [];
        $values = [];
        $names = [];

        foreach ($topAgencies as $row) {
            $agencyName = Agency::where('code', $row->code_agency)->first()?->name_corporative
                ?? "Agencia: {$row->code_agency}";

            $labels[] = $agencyName;
            $names[] = $agencyName;
            $values[] = (int) $row->total;
        }

        return [
            'datasets' => [
                $this->makeBarDataset(
                    "Top 15 agencias - {$monthName} ({$year})",
                    $values,
                    ['names' => $names],
                ),
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        $livewireId = (string) $this->getId();

        if ($this->selectedMonth !== null) {
            $onClickJs = '() => {}';
        } else {
            $onClickJs = <<<JS
(event, elements) => {
                if (!elements || !elements.length) {
                    return;
                }

                const index = elements[0].index;
                const month = index + 1;
                const component = window.Livewire?.find('{$livewireId}');
                component?.call('openMonthDetail', month);
            }
JS;
        }

        $tooltipFooter = $this->selectedMonth === null
            ? 'Haz clic para ver detalle'
            : '';

        return RawJs::make(<<<JS
        {
            onClick: {$onClickJs},
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 8, right: 8, bottom: 4, left: 4 }
            },
            interaction: {
                mode: 'nearest',
                intersect: true,
                axis: 'xy'
            },
            datasets: {
                bar: {
                    categoryPercentage: 0.92,
                    barPercentage: 0.98
                }
            },
            elements: {
                bar: {
                    borderWidth: 1.25,
                    borderRadius: 10,
                    inflateAmount: 0.6,
                    hoverBorderWidth: 2.5,
                    hoverBorderColor: 'rgba(255, 255, 255, 0.92)'
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    position: 'nearest',
                    xAlign: 'center',
                    yAlign: 'bottom',
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    footerColor: 'rgba(235, 235, 245, 0.7)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                    caretSize: 6,
                    caretPadding: 8,
                    titleFont: {
                        size: 14,
                        weight: '700',
                        family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                    },
                    bodyFont: {
                        size: 13,
                        weight: '500',
                        family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                    },
                    titleSpacing: 0,
                    titleMarginBottom: 8,
                    bodySpacing: 6,
                    footerSpacing: 8,
                    displayColors: true,
                    usePointStyle: true,
                    boxWidth: 12,
                    boxHeight: 12,
                    boxPadding: 8,
                    multiKeyBackground: 'rgba(255, 255, 255, 0.08)',
                    callbacks: {
                        title: function(context) {
                            const item = context[0];
                            const dataset = item.dataset || {};

                            if (dataset.names && dataset.names[item.dataIndex]) {
                                return dataset.names[item.dataIndex];
                            }

                            return item.label;
                        },
                        label: function(context) {
                            return ' Cotizaciones: ' + context.raw;
                        },
                        footer: () => '{$tooltipFooter}'
                    }
                }
            },
            scales: {
                x: {
                    stacked: false,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.1)'
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        color: () => document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                        font: {
                            size: 13,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
                    }
                },
                y: {
                    stacked: false,
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.12)'
                    },
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                        color: () => document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                        font: {
                            size: 13,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
                    }
                }
            },
            animation: {
                duration: 900,
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
