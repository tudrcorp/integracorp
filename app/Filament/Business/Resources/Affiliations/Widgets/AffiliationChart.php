<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class AffiliationChart extends ChartWidget
{
    protected ?string $heading = 'RESUMEN DE AFILIACIONES';

    protected ?string $maxHeight = '300px';

    protected ?string $description = 'Visualización mensual de afiliaciones con desglose por mes.';

    protected function getExtraAttributes(): array
    {
        return [
            'style' => 'height:50vh; width:100%;',
        ];
    }

    protected function getData(): array
    {
        $data = Trend::query(Affiliation::query()->where('status', 'ACTIVA')->whereYear('created_at', now()->year))
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        // Paleta de colores minimalista (uno por mes)
        $minimalistColors = [
            '#94a3b8',
            '#93c5fd',
            '#60a5fa',
            '#3b82f6',
            '#2563eb',
            '#1d4ed8',
            '#1e40af',
            '#1e3a8a',
            '#64748b',
            '#475569',
            '#334155',
            '#0f172a'
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Total Ventas (US$)',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    'backgroundColor' => $minimalistColors,
                    'borderRadius' => 8, // Barras redondeadas modernas
                    // Se elimina el color fijo negro para permitir que JS gestione el hover dinámicamente
                ],
            ],
            'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<JS
        {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        // Forzamos a que solo muestre números enteros
                        stepSize: 1,
                        precision: 0,
                        callback: function(value) {
                            if (Math.floor(value) === value) {
                                return value.toLocaleString('de-DE');
                            }
                        },
                        font: { size: 11 }
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: { display: false } 
                }
            },
            plugins: {
                legend: { display: false }, 
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#1e293b',
                    bodyColor: '#1e293b',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            let value = context.parsed.y;
                            return 'Afiliaciones: ' + value.toLocaleString('de-DE');
                        }
                    }
                }
            },
            hover: {
                mode: 'nearest',
                intersect: true
            },
            elements: {
                bar: {
                    hoverBackgroundColor: function(context) {
                        let color = context.dataset.backgroundColor[context.dataIndex];
                        return color + 'CC'; 
                    },
                    hoverBorderWidth: 2,
                    hoverBorderColor: 'rgba(0,0,0,0.1)'
                }
            },
            animation: {
                duration: 2000,
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
