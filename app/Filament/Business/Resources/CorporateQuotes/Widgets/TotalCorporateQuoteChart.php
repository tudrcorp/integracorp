<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CorporateQuotes\Widgets;

use App\Models\CorporateQuote;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class TotalCorporateQuoteChart extends ChartWidget
{
    protected string $view = 'filament.widgets.total-corporate-quote-chart';

    protected string $color = 'gray';

    protected ?string $heading = 'HISTORIAL DE COTIZACIONES CORPORATIVAS MENSUALES';

    protected ?string $description = 'Histórico mensual de cotizaciones corporativas.';

    protected ?string $maxHeight = '480px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = null;

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
            '#67e8f9',
            '#22d3ee',
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
     * @return array<string, mixed>
     */
    protected function makeBarDataset(string $label, array $data): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'backgroundColor' => $this->buildBackgroundColors(count($data)),
            'borderColor' => 'rgba(0,0,0,0.1)',
            'borderWidth' => 1.25,
            'borderRadius' => 8,
            'borderSkipped' => false,
        ];
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        $dataTrend = Trend::query(
            CorporateQuote::query()->whereYear('created_at', $year)
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
                $this->makeBarDataset("Cotizaciones corporativas ({$year})", $values),
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = 'default';
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
                            return context[0].label;
                        },
                        label: function(context) {
                            return ' Cotizaciones: ' + context.raw;
                        }
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
