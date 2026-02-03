<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Filament\Support\RawJs;

class SaleYearChart extends ChartWidget
{
    protected ?string $heading = 'RESUMEN DE VENTAS ANUAL';

    protected ?string $description = 'Visualización mensual de ingresos totales con desglose por periodos.';


    protected function getData(): array
    {
        $data = Trend::query(Sale::query()->whereYear('created_at', now()->year))
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->sum('total_amount');

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
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        // Formato: 10.568,98
                        callback: function(value) {
                            return '$' + value.toLocaleString('de-DE', { minimumFractionDigits: 2 });
                        },
                        font: { size: 11 }
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
                            return 'Ventas: $' + value.toLocaleString('de-DE', { minimumFractionDigits: 2 });
                        }
                    }
                }
            },
            // Configuración de interacción para oscurecer el color base
            hover: {
                mode: 'nearest',
                intersect: true
            },
            elements: {
                bar: {
                    // Al hacer hover, reducimos el brillo del color original
                    hoverBackgroundColor: function(context) {
                        let color = context.dataset.backgroundColor[context.dataIndex];
                        // Añadimos una capa de negro muy transparente para oscurecer
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
