<?php

namespace App\Filament\Business\Widgets;

use App\Filament\Business\Resources\Agencies\Widgets\TotalSaleForEstructureChart;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class SalesForAgency extends ChartWidget
{
    protected static ?int $sort = 5;

    /**
     * Misma envoltura y altura de canvas que en gestión de agencias
     * (@see TotalSaleForEstructureChart).
     */
    protected string $view = 'filament.widgets.total-sale-for-estructure-chart';

    protected string $color = 'gray';

    public int $chartKey = 0;

    protected ?string $heading = 'Ventas por Agencia';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '440px';

    protected ?string $description = 'Ventas por agencia. Selecciona el año y, si quieres, un mes concreto.';

    public ?string $filter = null;

    /** Mes dentro del año seleccionado: 1–12, o "0" para todo el año. */
    public ?string $monthFilter = null;

    public function mount(): void
    {
        parent::mount();

        $this->filter = $this->filter ?? (string) Carbon::now()->year;
        $this->monthFilter ??= '0';
    }

    /**
     * Opciones del segundo select (mismo estilo que el filtro por año).
     *
     * @return array<string, string>
     */
    public function getMonthFilterOptions(): array
    {
        $locale = app()->getLocale();
        $options = ['0' => 'Todo el año'];

        for ($m = 1; $m <= 12; $m++) {
            $label = Carbon::createFromDate((int) $this->filter ?: Carbon::now()->year, $m, 1)
                ->locale($locale)
                ->translatedFormat('F');
            $options[(string) $m] = ucfirst($label);
        }

        return $options;
    }

    protected function getFilters(): ?array
    {
        $now = Carbon::now();
        $filters = [];
        for ($i = 0; $i < 3; $i++) {
            $y = $now->year - $i;
            $filters[(string) $y] = (string) $y;
        }

        return $filters;
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? Carbon::now()->year);
        $monthRaw = (int) ($this->monthFilter ?? 0);

        return TotalSaleForEstructureChart::buildChartData([
            'filter' => 'year',
            'year' => $year,
            'month' => ($monthRaw >= 1 && $monthRaw <= 12) ? $monthRaw : null,
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Mismas opciones Chart.js que el widget de agencias (barras a ancho completo).
     * Sin onClick: en el escritorio no hay drill-down por agencia.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
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
                        footer: () => ''
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
                        color: '#8e8e93',
                        font: {
                            size: 10,
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
                        color: '#8e8e93',
                        font: {
                            size: 10,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        },
                        callback: (value) => '$' + Number(value).toLocaleString()
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
}
