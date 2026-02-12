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

    protected ?string $maxHeight = '350px';


    protected function getData(): array
    {
        $data = Trend::query(Sale::query()->whereYear('created_at', now()->year))
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->sum('total_amount');

        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        foreach ($labels as $label) {
            $backgroundColors[] = $this->getRandomVibrantColor();
        }

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
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 8, // Barras redondeadas modernas
                    // Se elimina el color fijo negro para permitir que JS gestione el hover dinámicamente
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getRandomVibrantColor(): string
    {
        $r = mt_rand(50, 200);
        $g = mt_rand(50, 200);
        $b = mt_rand(50, 200);
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }


    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<JS
            {
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1500,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 25,
                            font: { size: 12 }
                        },
                    },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + context.raw + '%';
                            }
                        }
                    },
                    // Configuración para mostrar texto DENTRO de las porciones
                    datalabels: {
                        display: true,
                        color: '#ffffff',
                        anchor: 'center',
                        align: 'center',
                        offset: 0,
                        font: {
                            size: 18,
                            weight: 'bold',
                            family: 'sans-serif'
                        },
                        formatter: (value) => {
                            return value > 0 ? value + '%' : '';
                        },
                        // Sombra para mejorar legibilidad sobre colores claros
                        textShadowColor: 'rgba(0, 0, 0, 0.5)',
                        textShadowBlur: 4
                    }
                },
                //cuadricula
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { stepSize: 1 },
                        grid: {
                            display: true,
                            color: 'rgba(156, 163, 175, 0.2)', // Gris suave adaptable
                            drawBorder: false
                        }
                    },
                    x: { 
                        grid: { 
                            display: true,
                            color: 'rgba(156, 163, 175, 0.1)', // Líneas verticales más tenues
                            drawBorder: false
                        } 
                    }
                },
                // Optimización de espacio
                layout: {
                    padding: 20
                }
            }
        JS);
    }
}
