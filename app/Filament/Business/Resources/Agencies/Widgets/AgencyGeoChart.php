<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Models\Agency;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

// class AgencyGeoChart extends ChartWidget
// {
//     protected ?string $heading = 'Distribución de Agencias por Estado';
//     protected static ?int $sort = 2;
//     protected ?string $pollingInterval = null;
//     protected int|string|array $columnSpan = 'full';
//     protected ?string $maxHeight = '500px';

//     protected function getType(): string
//     {
//         return 'doughnut';
//     }

//     protected function getData(): array
//     {
//         $distribution = Cache::remember('agency_geo_distribution:v10', 3600, function () {
//             return Agency::query()
//                 ->join('states', 'agencies.state_id', '=', 'states.id')
//                 ->selectRaw('states.definition as state_name, COUNT(*) as total')
//                 ->where('agencies.status', 'ACTIVO')
//                 ->groupBy('states.definition')
//                 ->orderByDesc('total')
//                 ->pluck('total', 'state_name')
//                 ->toArray();
//         });

//         $vibrantPalette = [
//             '#FF2D55', // Rosa Apple
//             '#5856D6', // Púrpura Apple
//             '#34C759', // Verde Apple
//             '#FF9500', // Naranja Apple
//             '#007AFF', // Azul Apple
//             '#AF52DE', // Índigo
//             '#FFCC00', // Amarillo
//             '#5AC8FA', // Cian
//             '#FF3B30', // Rojo
//             '#2dd4bf', // Teal
//             '#f472b6', // Rosa fuerte
//             '#a78bfa', // Violeta claro
//         ];

//         $labels = array_keys($distribution);
//         $data = array_values($distribution);

//         $backgroundColors = array_map(function ($index) use ($vibrantPalette) {
//             return $vibrantPalette[$index % count($vibrantPalette)];
//         }, array_keys($data));

//         return [
//             'labels' => $labels,
//             'datasets' => [
//                 [
//                     'label' => 'Agencias Activas',
//                     'data' => $data,
//                     'backgroundColor' => $backgroundColors,
//                     'borderColor' => 'transparent',
//                     'hoverOffset' => 35, // Aumentado para resaltar más la pieza al frente
//                     'hoverBorderWidth' => 3,
//                     'hoverBorderColor' => '#ffffff',
//                     'borderRadius' => 4,
//                 ],
//             ],
//         ];
//     }

//     protected function getOptions(): RawJs
//     {
//         return RawJs::make(<<<'JS'
//         {
//             responsive: true,
//             maintainAspectRatio: false,
//             cutout: '65%',
//             layout: {
//                 padding: 40 // Espacio extra para permitir que el segmento resalte sin cortarse
//             },
//             plugins: {
//                 legend: {
//                     display: true,
//                     position: 'bottom',
//                     labels: {
//                         usePointStyle: true,
//                         pointStyle: 'circle',
//                         padding: 25,
//                         font: {
//                             size: 11,
//                             weight: '600'
//                         },
//                         color: 'gray'
//                     }
//                 },
//                 tooltip: {
//                     backgroundColor: 'rgba(255, 255, 255, 0.95)',
//                     titleColor: '#1e293b',
//                     bodyColor: '#1e293b',
//                     borderColor: '#e2e8f0',
//                     borderWidth: 1,
//                     padding: 12,
//                     boxPadding: 6,
//                     usePointStyle: true,
//                     callbacks: {
//                         label: (context) => ` ${context.label}: ${context.raw} agencias (${context.formattedValue}%)`
//                     }
//                 }
//             },
//             // Configuraciones de interacción para el resaltado
//             hover: {
//                 mode: 'nearest',
//                 intersect: true
//             },
//             animation: {
//                 animateScale: true,
//                 animateRotate: true,
//                 duration: 1500,
//                 easing: 'easeOutQuart'
//             },
//             // Efecto de énfasis al posicionar el cursor
//             onHover: (event, chartElement) => {
//                 event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
//             }
//         }
//         JS);
//     }

//     protected function getExtraAttributes(): array
//     {
//         return [
//             'class' => 'fi-geo-chart-widget shadow-2xl rounded-[32px] overflow-hidden border-none bg-white/60 dark:bg-gray-900/60 backdrop-blur-xl p-6',
//         ];
//     }
// }

class AgencyGeoChart extends ChartWidget
{
    protected ?string $heading = 'Distribución de Agencias por Estado';

    protected ?string $description = 'Análisis porcentual de agencias activas por estado.';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '400px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $distribution = Cache::remember('agency_geo_distribution:v10', 3600, function () {
            return Agency::query()
                ->join('states', 'agencies.state_id', '=', 'states.id')
                ->selectRaw('states.definition as state_name, COUNT(*) as total')
                ->where('agencies.status', 'ACTIVO')
                ->groupBy('states.definition')
                ->orderByDesc('total')
                ->pluck('total', 'state_name')
                ->toArray();
        });

        $vibrantPalette = [
            '#FF2D55', // Rosa Apple
            '#5856D6', // Púrpura Apple
            '#34C759', // Verde Apple
            '#FF9500', // Naranja Apple
            '#007AFF', // Azul Apple
            '#AF52DE', // Índigo
            '#FFCC00', // Amarillo
            '#5AC8FA', // Cian
            '#FF3B30', // Rojo
            '#2dd4bf', // Teal
            '#f472b6', // Rosa fuerte
            '#a78bfa', // Violeta claro
        ];

        $labels = array_keys($distribution);
        $data = array_values($distribution);

        $backgroundColors = array_map(function ($index) use ($vibrantPalette) {
            return $vibrantPalette[$index % count($vibrantPalette)];
        }, array_keys($data));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Agencias Activas',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => 'transparent',
                    'hoverOffset' => 35, // Aumentado para resaltar más la pieza al frente
                    'hoverBorderWidth' => 3,
                    'hoverBorderColor' => '#ffffff',
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            layout: {
                padding: 40 // Espacio extra para permitir que el segmento resalte sin cortarse
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        font: {
                            size: 11,
                            weight: '600'
                        },
                        color: 'gray'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#1e293b',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: (context) => ` ${context.label}: ${context.raw} agencias `
                    }
                }
            },
            // Configuraciones de interacción para el resaltado
            hover: {
                mode: 'nearest',
                intersect: true
            },
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1500,
                easing: 'easeOutQuart'
            },
            // Efecto de énfasis al posicionar el cursor
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            }
        }
        JS);
    }

    protected function getExtraAttributes(): array
    {
        return [
            'class' => 'fi-geo-chart-widget shadow-2xl rounded-[32px] overflow-hidden border-none bg-white/60 dark:bg-gray-900/60 backdrop-blur-xl p-6',
        ];
    }
}
