<?php

namespace App\Filament\Business\Resources\Helpdesks\Widgets;

use App\Models\HelpDesk;
use App\Support\HelpdeskStatusYearlyChartSeries;
use Filament\Actions\Action;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class HelpdeskStatusWeeklyChart extends ChartWidget
{
    protected string $view = 'filament.widgets.helpdesk-status-weekly-chart';

    protected string $color = 'gray';

    protected ?string $heading = 'Estado anual de tickets';

    protected ?string $description = 'Distribución anual por meses. Haz click en un mes para ver el detalle por colaborador ordenado por más tickets terminados.';

    protected ?string $maxHeight = '440px';

    protected int|string|array $columnSpan = 'full';

    public int $chartKey = 0;

    public int $year = 0;

    public ?int $selectedMonth = null;

    public function mount(): void
    {
        parent::mount();

        $this->resetYear();
    }

    public function resetYear(): void
    {
        $this->year = (int) now()->year;
        $this->chartKey++;
        $this->updateChartData();
    }

    public function updatedYear(mixed $value): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $year = (int) $value;
        if ($year < 2000 || $year > 2100) {
            return;
        }

        $this->year = $year;
        $this->selectedMonth = null;
        $this->chartKey++;
        $this->updateChartData();
    }

    protected function getFilters(): ?array
    {
        $now = now();
        $filters = [];

        for ($i = 0; $i < 3; $i++) {
            $y = $now->year - $i;
            $filters[(string) $y] = (string) $y;
        }

        return $filters;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetAnnualView')
                ->label('Volver a vista anual')
                ->icon('heroicon-m-arrow-path')
                ->size('sm')
                ->color('gray')
                ->visible(fn (): bool => $this->selectedMonth !== null)
                ->action(function (): void {
                    $this->selectedMonth = null;
                    $this->updateChartData();
                }),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $statuses = HelpdeskStatusYearlyChartSeries::statuses();
        $year = $this->resolveYear();

        if ($this->selectedMonth !== null) {
            /** @var Collection<int, \App\Models\HelpDesk> $records */
            $records = $this->baseTicketsQuery()
                ->whereIn('status', $statuses)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $this->selectedMonth)
                ->with(['rrhhColaboradores:id,fullName'])
                ->get(['id', 'status', 'created_at']);

            return HelpdeskStatusYearlyChartSeries::detailChartDataFromRecords($records);
        }

        /** @var Collection<int, \App\Models\HelpDesk> $records */
        $records = $this->baseTicketsQuery()
            ->whereIn('status', $statuses)
            ->whereYear('created_at', $year)
            ->get(['status', 'created_at']);

        return HelpdeskStatusYearlyChartSeries::chartJsDataFromRecords($records, year: $year);
    }

    protected function getOptions(): RawJs
    {
        $livewireId = (string) $this->getId();

        return RawJs::make(<<<JS
        {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 8, right: 8, bottom: 14, left: 4 }
            },
            interaction: {
                mode: 'nearest',
                intersect: true,
                axis: 'xy'
            },
            onClick: (event, elements) => {
                if (!elements || !elements.length) {
                    return;
                }

                const index = elements[0].index;
                const month = index + 1;
                const component = window.Livewire?.find('{$livewireId}');
                component?.call('toggleMonthDetail', month);
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            datasets: {
                bar: {
                    categoryPercentage: 0.82,
                    barPercentage: 0.92
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
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#000000',
                        boxWidth: 12,
                        boxHeight: 12,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            size: 13,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
                    }
                },
                tooltip: {
                    enabled: true,
                    mode: 'nearest',
                    intersect: true,
                    position: 'nearest',
                    xAlign: 'center',
                    yAlign: 'bottom',
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
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
                    displayColors: true
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
                        maxRotation: 0,
                        minRotation: 0,
                        padding: 6,
                        color: '#000000',
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
                        color: '#000000',
                        font: {
                            size: 13,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
                    }
                }
            },
            animation: {
                duration: 700,
                easing: 'easeOutCubic'
            },
            animations: {
                x: {
                    duration: 550,
                    easing: 'easeOutCubic'
                },
                y: {
                    duration: 700,
                    easing: 'easeOutCubic'
                }
            },
            transitions: {
                active: {
                    animation: {
                        duration: 300
                    }
                },
                resize: {
                    animation: {
                        duration: 300
                    }
                }
            },
        }
        JS);
    }

    public function openMonthDetail(int $month): void
    {
        if ($month < 1 || $month > 12) {
            return;
        }

        $this->selectedMonth = $month;
        $this->updateChartData();
    }

    public function resetToAnnual(): void
    {
        $this->selectedMonth = null;
        $this->updateChartData();
    }

    public function toggleMonthDetail(int $month): void
    {
        if ($this->selectedMonth !== null) {
            $this->resetToAnnual();

            return;
        }

        $this->openMonthDetail($month);
    }

    private function resolveYear(): int
    {
        if ($this->year <= 0) {
            $this->year = (int) now()->year;
        }

        return $this->year;
    }

    private function baseTicketsQuery(): Builder
    {
        return HelpDesk::query();
    }
}
