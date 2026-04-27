<?php

namespace App\Filament\Business\Widgets;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AffiliationIndividualChart extends ChartWidget
{
    protected string $view = 'filament.widgets.year-filter-fixed-height-chart';

    protected string $color = 'gray';

    protected ?string $heading = 'Afiliaciones Individuales y Corporativas por mes';

    protected static ?int $sort = 1;

    protected ?string $maxHeight = '400px';

    protected ?string $description = 'Total de afiliaciones por mes. Selecciona el año para ver el movimiento.';

    public ?string $filter = null;

    public int $chartKey = 0;

    public function mount(): void
    {
        $this->filter = $this->filter ?? (string) Carbon::now()->year;
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
        $anio = (int) ($this->filter ?? Carbon::now()->year);
        $isAccountManager = Auth::user()->is_accountManagers == 1;
        $userId = Auth::user()->id;

        $scopeAffiliation = fn ($q) => $isAccountManager ? $q->where('ownerAccountManagers', $userId) : $q;
        $scopeCorporate = fn ($q) => $isAccountManager ? $q->where('ownerAccountManagers', $userId) : $q;

        $labels = [];
        $individuales = [];
        $corporativas = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $inicio = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
            $fin = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

            $labels[] = $inicio->translatedFormat('F');

            $individuales[] = (clone $scopeAffiliation(Affiliation::query()))
                ->whereBetween('created_at', [$inicio, $fin])
                ->count();

            $corporativas[] = (clone $scopeCorporate(AffiliationCorporate::query()))
                ->whereBetween('created_at', [$inicio, $fin])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Afiliaciones Individuales',
                    'data' => $individuales,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Afiliaciones Corporativas',
                    'data' => $corporativas,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

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
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#8e8e93',
                        font: {
                            size: 11,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        },
                        usePointStyle: true,
                        boxWidth: 10,
                        boxHeight: 10
                    }
                },
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
                        callback: (value) => Number(value).toLocaleString()
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
