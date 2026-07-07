<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Widgets;

use App\Models\CorporateQuoteRequest;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Facades\DB;

class CorporateQuoteRequestCreatorsChart extends ChartWidget
{
    protected string $view = 'filament.widgets.corporate-quote-request-creators-chart';

    protected string $color = 'gray';

    protected ?string $heading = 'SOLICITUDES POR QUIEN LAS CREÓ';

    protected ?string $description = 'Top 15 usuarios con más solicitudes Dress Taylor en el periodo seleccionado.';

    protected ?string $maxHeight = '420px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = null;

    public ?int $month = null;

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

    public function mount(): void
    {
        $this->filter ??= (string) now()->year;
        $this->month ??= now()->month;
    }

    public function isFullYearPeriod(): bool
    {
        return (int) ($this->month ?? 0) === 0;
    }

    /**
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
     * @return array<int|string, string>
     */
    public function getMonthSelectOptions(): array
    {
        $year = (int) ($this->filter ?? now()->year);
        $now = Carbon::now();
        $maxMonth = ($year === (int) $now->year) ? (int) $now->month : 12;

        $options = ['0' => 'Todo el año'];
        $locale = app()->getLocale();
        for ($m = 1; $m <= $maxMonth; $m++) {
            $options[(string) $m] = ucfirst(Carbon::createFromDate(2000, $m, 1)->locale($locale)->translatedFormat('F'));
        }

        return $options;
    }

    public function updatedFilter($value): void
    {
        $year = (int) $value;

        if ($this->isFullYearPeriod()) {
            $this->month = 0;
            $this->updateChartData();

            return;
        }

        $now = Carbon::now();
        $maxMonth = ($year === (int) $now->year) ? (int) $now->month : 12;

        $month = (int) ($this->month ?? $maxMonth);
        $this->month = max(1, min($maxMonth, $month));

        $this->updateChartData();
    }

    public function updatedMonth($value): void
    {
        $this->month = (int) $value;
        $this->updateChartData();
    }

    public function updateChartData(): void
    {
        $newDataChecksum = $this->generateDataChecksum();

        if ($newDataChecksum !== $this->dataChecksum) {
            $this->dataChecksum = $newDataChecksum;

            $this->dispatch('updateChartData', data: $this->getCachedData());
        }

        if ($this->isFullYearPeriod()) {
            $this->dispatch(
                'updateCorporateQuoteRequestMonthlyBreakdownChartData',
                data: $this->getMonthlyBreakdownChartData(),
            );
        }
    }

    protected function getData(): array
    {
        if ($this->isFullYearPeriod()) {
            return $this->buildTopCreatorsYearChartData();
        }

        return $this->buildTopCreatorsMonthChartData();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTopCreatorsYearChartData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        $topCreators = CorporateQuoteRequest::query()
            ->select([
                'created_by',
                DB::raw('count(*) as total'),
            ])
            ->whereYear('created_at', $year)
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->groupBy('created_by')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        return $this->formatTopCreatorsChart(
            $topCreators,
            "Top creadores · todo el año {$year}",
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTopCreatorsMonthChartData(): array
    {
        $year = (int) ($this->filter ?? now()->year);
        $month = max(1, min(12, (int) ($this->month ?? now()->month)));

        $topCreators = CorporateQuoteRequest::query()
            ->select([
                'created_by',
                DB::raw('count(*) as total'),
            ])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->groupBy('created_by')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $monthName = ucfirst(Carbon::createFromDate($year, $month, 1)->locale(app()->getLocale())->translatedFormat('F'));

        return $this->formatTopCreatorsChart(
            $topCreators,
            "Top creadores · {$monthName} {$year}",
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{created_by: mixed, total: mixed}>  $topCreators
     * @return array<string, mixed>
     */
    protected function formatTopCreatorsChart($topCreators, string $label): array
    {
        $labels = [];
        $values = [];
        $names = [];

        foreach ($topCreators as $row) {
            $creatorName = trim((string) $row->created_by) !== ''
                ? (string) $row->created_by
                : 'Sin registro';

            $labels[] = $creatorName;
            $names[] = $creatorName;
            $values[] = (int) $row->total;
        }

        return [
            'datasets' => [
                [
                    'label' => $label,
                    'data' => $values,
                    'backgroundColor' => $this->buildBackgroundColors(count($values)),
                    'borderColor' => 'rgba(0,0,0,0.1)',
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'names' => $names,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMonthlyBreakdownChartData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        $dataTrend = Trend::query(
            CorporateQuoteRequest::query()->whereYear('created_at', $year)
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
                [
                    'label' => "Solicitudes por mes · {$year}",
                    'data' => $values,
                    'backgroundColor' => $this->buildBackgroundColors(count($values)),
                    'borderColor' => 'rgba(0,0,0,0.1)',
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function getMonthlyBreakdownChartOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
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
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                    callbacks: {
                        label: function(context) {
                            return ' Solicitudes: ' + context.raw;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.1)'
                    },
                    ticks: {
                        color: () => document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                        font: {
                            size: 12,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.12)'
                    },
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                        color: () => document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000'
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

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            indexAxis: 'y',
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
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
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
                            return ' Solicitudes: ' + context.raw;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.12)'
                    },
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                        color: () => document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000'
                    }
                },
                y: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        color: () => document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                        font: {
                            size: 12,
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
