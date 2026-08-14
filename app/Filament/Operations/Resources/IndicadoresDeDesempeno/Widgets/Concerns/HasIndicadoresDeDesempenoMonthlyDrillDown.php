<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\Concerns;

use App\Support\IndicadoresDeDesempeno\IndicadoresDeDesempenoCollaboratorAccess;
use App\Support\IndicadoresDeDesempeno\IndicadoresDeDesempenoTimeBuckets;
use Filament\Support\RawJs;

trait HasIndicadoresDeDesempenoMonthlyDrillDown
{
    public ?int $selectedMonth = null;

    public ?int $selectedWeek = null;

    public int $chartKey = 0;

    public function openMonthDetail(int $month): void
    {
        if ($month < 1 || $month > 12) {
            return;
        }

        $this->selectedMonth = $month;
        $this->selectedWeek = null;
        $this->chartKey++;
        $this->updateChartData();
    }

    public function openWeekDetail(int $week): void
    {
        if ($this->selectedMonth === null) {
            return;
        }

        $maxWeeks = IndicadoresDeDesempenoTimeBuckets::weeksInMonth(
            $this->resolvedYear(),
            $this->selectedMonth,
        );

        if ($week < 1 || $week > $maxWeeks) {
            return;
        }

        $this->selectedWeek = $week;
        $this->chartKey++;
        $this->updateChartData();
    }

    public function resetToWeekly(): void
    {
        $this->selectedWeek = null;
        $this->chartKey++;
        $this->updateChartData();
    }

    public function resetToMonthly(): void
    {
        $this->selectedMonth = null;
        $this->selectedWeek = null;
        $this->chartKey++;
        $this->updateChartData();
    }

    public function updatedFilter(mixed $value): void
    {
        unset($value);

        $this->selectedMonth = null;
        $this->selectedWeek = null;
        $this->chartKey++;
    }

    protected function resolvedYear(): int
    {
        return (int) ($this->filter ?? now()->year);
    }

    protected function scopedCollaborator(): ?string
    {
        return IndicadoresDeDesempenoCollaboratorAccess::restrictToCollaborator();
    }

    protected function selectedMonthLabel(): string
    {
        if ($this->selectedMonth === null) {
            return '';
        }

        return IndicadoresDeDesempenoTimeBuckets::monthLabel($this->selectedMonth);
    }

    protected function selectedWeekLabel(): string
    {
        if ($this->selectedWeek === null) {
            return '';
        }

        return "Semana {$this->selectedWeek}";
    }

    /**
     * @return array<string, string>
     */
    protected function yearFilters(): array
    {
        $now = now();
        $filters = [];

        for ($i = 0; $i < 5; $i++) {
            $y = $now->year - $i;
            $filters[(string) $y] = (string) $y;
        }

        return $filters;
    }

    protected function monthlyDrillDownOptions(bool $showLegend = false): RawJs
    {
        $livewireId = (string) $this->getId();

        if ($this->selectedWeek !== null) {
            $onClickJs = '() => {}';
            $cursorJs = 'event.native.target.style.cursor = \'default\';';
        } elseif ($this->selectedMonth !== null) {
            $onClickJs = <<<JS
(event, elements) => {
                if (!elements || !elements.length) {
                    return;
                }

                const index = elements[0].index;
                const week = index + 1;
                const component = window.Livewire?.find('{$livewireId}');
                component?.call('openWeekDetail', week);
            }
JS;
            $cursorJs = 'event.native.target.style.cursor = chartElement[0] ? \'pointer\' : \'default\';';
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
            $cursorJs = 'event.native.target.style.cursor = chartElement[0] ? \'pointer\' : \'default\';';
        }

        $legendJs = $showLegend
            ? <<<'JS'
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
                        },
                    },
                },
JS
            : <<<'JS'
legend: {
                    display: false,
                },
JS;

        return RawJs::make(<<<JS
        {
            responsive: true,
            maintainAspectRatio: false,
            datasets: {
                bar: {
                    categoryPercentage: 0.82,
                    barPercentage: 0.92,
                },
            },
            onClick: {$onClickJs},
            onHover: (event, chartElement) => {
                {$cursorJs}
            },
            plugins: {
                {$legendJs}
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                },
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: '#000000',
                        font: {
                            size: 13,
                        },
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                        color: '#000000',
                        font: {
                            size: 13,
                        },
                    },
                },
            },
        }
        JS);
    }

    protected function brighterGlassFill(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $matches)) {
            $alpha = min(0.95, (float) $matches[4] + 0.12);

            return "rgba({$matches[1]}, {$matches[2]}, {$matches[3]}, {$alpha})";
        }

        return $rgba;
    }
}
