<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use App\Models\ServiceProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class AffiliationSupplierChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliations::class;
    }
    public function mount(): void
    {
        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected ?string $maxHeight = '300px';

    protected ?string $heading = 'DISTRIBUCIÓN DE AFILIACIONES POR PLAN';

    protected ?string $description = 'Análisis porcentual y cuantitativo de afiliaciones por plan en el mes actual.';

    // Ocupar medio ancho para que se vea mejor en el dashboard junto a otros widgets
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        // Rango del mes actual
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        //Proveedores
        $suppliers = ServiceProvider::all('name')->toArray();

        $supplierIds = [];
        for ($i = 0; $i < count($suppliers); $i++) {

            // $count= Affiliation::where('service_providers', 'like', '%' . $suppliers[$i]['name'] . '%')
            //     ->whereBetween('created_at', ['2026-01-01 00:00:00', '2026-01-31 23:59:59'])
            //     ->count();

            /**
             * @var mixed
             * @version 2.0.0
             */
            $count = $this->getPageTableQuery()
                ->where('service_providers', 'like', '%' . $suppliers[$i]['name'] . '%')
                ->count();

            array_push($supplierIds, ['name' => $suppliers[$i]['name'], 'count' => $count]);
        }

        $totalCountSuppliers = array_sum(array_column($supplierIds, 'count'));

        // Evitar división por cero
        if ($totalCountSuppliers === 0) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [['data' => [0], 'backgroundColor' => ['#e5e7eb']]]
            ];
        }

        // Mapeo de IDs a nombres de planes
        $suppliers = [
            1 => ['label' => 'ATENMEDI', 'color' => '#9ce1ff'], // Esmeralda
            2 => ['label' => 'ILS', 'color' => '#25b4e7'],   // Ámbar
            3 => ['label' => 'TDEC', 'color' => '#2d89ca'], // Rojo
        ];

        $counts = [
            'ATENMEDI' => $supplierIds[0]['count'], 
            'ILS' => $supplierIds[1]['count'],
            'TDEC' => $supplierIds[2]['count'],
        ];

        // Cálculo de porcentajes
        $data = [
            round(($counts['ATENMEDI'] * 100) / $totalCountSuppliers),
            round(($counts['ILS'] * 100) / $totalCountSuppliers),
            round(($counts['TDEC'] * 100) / $totalCountSuppliers),
        ];

        return [
            'labels' => [$suppliers[1]['label'], $suppliers[2]['label'], $suppliers[3]['label']],
            'datasets' => [
                [
                    'label' => 'Porcentaje',
                    'data' => $data,
                    'backgroundColor' => [
                        $suppliers[1]['color'],
                        $suppliers[2]['color'],
                        $suppliers[3]['color'],
                    ],
                    'label' => 'Dataset 1',
                    // Efectos de interacción mejorados
                    'hoverOffset' => 30, // El segmento sobresale significativamente al pasar el mouse
                    'borderColor' => '#ffffff',
                    'borderWidth' => 3,
                    'hoverBorderColor' => '#ffffff',
                    'hoverBorderWidth' => 5,
                ],
            ],
        ];
    }

    //v2
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
                // Optimización de espacio
                layout: {
                    padding: 20
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
