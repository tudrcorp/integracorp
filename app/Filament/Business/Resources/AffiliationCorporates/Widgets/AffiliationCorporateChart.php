<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class AffiliationCorporateChart extends ChartWidget
{
    protected string $view = 'filament.widgets.affiliation-corporate-chart';

    protected ?string $heading = 'RESUMEN DE AFILIACIONES Y AFILIADOS CORPORATIVOS';

    protected ?string $description = 'Visualización por año. Haz clic en un año para ver los meses, y en un mes para el detalle diario de afiliados.';

    protected ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 1;

    public ?int $selectedYear = null;

    public ?int $selectedMonth = null;

    public int $chartKey = 0;

    public function openYearDetail(int $year): void
    {
        $availableYears = $this->availableYears();

        if (! in_array($year, $availableYears, true)) {
            return;
        }

        $this->selectedYear = $year;
        $this->selectedMonth = null;
        $this->cachedData = null;
        $this->chartKey++;
        $this->updateChartData();

        Notification::make()
            ->title("Vista mensual {$year}")
            ->body('Mostrando afiliaciones activas por mes. Haz clic en un mes para ver el detalle diario.')
            ->info()
            ->send();
    }

    public function openMonthDetail(int $month): void
    {
        if ($this->selectedYear === null || $month < 1 || $month > 12) {
            return;
        }

        $this->selectedMonth = $month;
        $this->cachedData = null;
        $this->chartKey++;
        $this->updateChartData();

        $monthLabel = ucfirst(
            Carbon::create($this->selectedYear, $month)
                ->locale(app()->getLocale())
                ->translatedFormat('F')
        );

        Notification::make()
            ->title("Detalle de Afiliados: {$monthLabel} {$this->selectedYear}")
            ->body('Mostrando el desglose diario de personas afiliadas.')
            ->info()
            ->send();
    }

    public function resetToYears(): void
    {
        $this->selectedYear = null;
        $this->selectedMonth = null;
        $this->cachedData = null;
        $this->chartKey++;
        $this->updateChartData();

        Notification::make()
            ->title('Vista por años')
            ->body('Regresando al resumen de afiliaciones por año.')
            ->success()
            ->send();
    }

    public function resetToMonths(): void
    {
        if ($this->selectedYear === null) {
            return;
        }

        $this->selectedMonth = null;
        $this->cachedData = null;
        $this->chartKey++;
        $this->updateChartData();

        Notification::make()
            ->title("Vista mensual {$this->selectedYear}")
            ->body('Regresando al resumen de afiliaciones por mes.')
            ->success()
            ->send();
    }

    public function selectedMonthLabel(): string
    {
        if ($this->selectedMonth === null || $this->selectedYear === null) {
            return '';
        }

        return ucfirst(
            Carbon::create($this->selectedYear, $this->selectedMonth)
                ->locale(app()->getLocale())
                ->translatedFormat('F')
        );
    }

    /**
     * @return list<int>
     */
    protected function availableYears(): array
    {
        $currentYear = now()->year;
        $years = [];

        for ($i = 0; $i < 5; $i++) {
            $years[] = $currentYear - $i;
        }

        return $years;
    }

    protected function getFilters(): ?array
    {
        return null;
    }

    protected function getData(): array
    {
        $backgroundColors = [];

        if ($this->selectedYear !== null && $this->selectedMonth !== null) {
            $year = $this->selectedYear;
            $startOfMonth = Carbon::create($year, $this->selectedMonth)->startOfMonth();
            $endOfMonth = Carbon::create($year, $this->selectedMonth)->endOfMonth();

            $data = Trend::query(
                AffiliateCorporate::query()
                    ->whereHas('affiliationCorporate', function ($query) use ($year): void {
                        $query->where('status', 'ACTIVA')
                            ->whereYear('created_at', $year);
                    })
            )
                ->between(start: $startOfMonth, end: $endOfMonth)
                ->perDay()
                ->count();

            $labels = $data->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('d'))->toArray();
            $monthLabel = $this->selectedMonthLabel();
            $datasetLabel = "Afiliados en {$monthLabel} ({$year})";
            $values = $data->map(fn (TrendValue $value) => (int) $value->aggregate)->toArray();
        } elseif ($this->selectedYear !== null) {
            $year = $this->selectedYear;
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
            $values = $data->map(fn (TrendValue $value) => (int) $value->aggregate)->toArray();
        } else {
            $years = array_reverse($this->availableYears());
            $labels = array_map(static fn (int $year): string => (string) $year, $years);
            $values = [];

            foreach ($years as $year) {
                $values[] = AffiliationCorporate::query()
                    ->where('status', 'ACTIVA')
                    ->whereYear('created_at', $year)
                    ->count();
            }

            $datasetLabel = 'Afiliaciones Activas por Año';
        }

        $palette = $this->barColors();

        foreach (array_keys($labels) as $index) {
            $backgroundColors[] = $palette[$index % count($palette)];
        }

        return [
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 4,
                    'barPercentage' => 0.7,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return list<string>
     */
    protected function barColors(): array
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
            '#67e8f9',
            '#22d3ee',
        ];
    }

    protected function getOptions(): RawJs
    {
        $livewireId = (string) $this->getId();

        if ($this->selectedMonth !== null) {
            $onClickJs = '() => {}';
            $cursorJs = 'event.native.target.style.cursor = \'default\';';
        } elseif ($this->selectedYear !== null) {
            $onClickJs = <<<JS
(event, elements) => {
                if (!elements || !elements.length) {
                    return;
                }

                const month = elements[0].index + 1;
                const component = window.Livewire?.find('{$livewireId}');
                component?.call('openMonthDetail', month);
            }
JS;
            $cursorJs = 'event.native.target.style.cursor = chartElement[0] ? \'pointer\' : \'default\';';
        } else {
            $onClickJs = <<<JS
(event, elements, chart) => {
                if (!elements || !elements.length) {
                    return;
                }

                const index = elements[0].index;
                const year = Number(chart.data.labels[index]);
                const component = window.Livewire?.find('{$livewireId}');
                component?.call('openYearDetail', year);
            }
JS;
            $cursorJs = 'event.native.target.style.cursor = chartElement[0] ? \'pointer\' : \'default\';';
        }

        return RawJs::make(<<<JS
        {
            onClick: {$onClickJs},
            onHover: (event, chartElement) => {
                {$cursorJs}
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
                    ticks: {
                        precision: 0,
                        color: '#9CA3AF',
                        font: { size: 10 }
                    },
                    grid: {
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.15)',
                    }
                },
                x: {
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(156, 163, 175, 0.1)',
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
