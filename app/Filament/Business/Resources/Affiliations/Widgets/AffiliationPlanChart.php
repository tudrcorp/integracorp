<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use App\Models\Sale;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;

class AffiliationPlanChart extends ChartWidget
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
        $affiliations = $this->getPageTableQuery()
            ->reorder()
            ->select('plan_id', DB::raw('count(*) as total'))
            ->groupBy('plan_id')
            ->get();
    // dd($affiliations);
        // dd($affiliations::select('plan_id', DB::raw('count(*) as total'))->groupBy('plan_id')->get());
        // Rango del mes actual
        // $startOfMonth = now()->startOfMonth();
        // $endOfMonth = now()->endOfMonth();

        // $start = $affiliations->first()->created_at;
        // $end = $affiliations->latest()->first()->created_at;

        // OPTIMIZACIÓN: Una sola consulta para obtener todos los conteos agrupados
        // $salesData = Affiliation::whereBetween('created_at', [$startOfMonth, $endOfMonth])
        $salesData = $this->getPageTableQuery()
            ->reorder()
            ->select('plan_id', DB::raw('count(*) as total'))
            ->groupBy('plan_id')
            ->get();
        // dd($salesData);

        $totalSales = $salesData->sum('total');

        // Evitar división por cero
        if ($totalSales === 0) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [['data' => [0], 'backgroundColor' => ['#e5e7eb']]]
            ];
        }

        // Mapeo de IDs a nombres de planes
        $plans = [
            1 => ['label' => 'Plan Inicial', 'color' => '#9ce1ff'], // Esmeralda
            2 => ['label' => 'Plan Ideal', 'color' => '#25b4e7'],   // Ámbar
            3 => ['label' => 'Plan Especial', 'color' => '#2d89ca'], // Rojo
        ];

        $counts = [
            'inicial' => $salesData->firstWhere('plan_id', 1)->total ?? 0,
            'ideal' => $salesData->firstWhere('plan_id', 2)->total ?? 0,
            'especial' => $salesData->firstWhere('plan_id', 3)->total ?? 0,
        ];

        // Cálculo de porcentajes
        $data = [
            round(($counts['inicial'] * 100) / $totalSales),
            round(($counts['ideal'] * 100) / $totalSales),
            round(($counts['especial'] * 100) / $totalSales),
        ];

        return [
            'labels' => [$plans[1]['label'], $plans[2]['label'], $plans[3]['label']],
            'datasets' => [
                [
                    'label' => 'Porcentaje',
                    'data' => $data,
                    'backgroundColor' => [
                        $plans[1]['color'],
                        $plans[2]['color'],
                        $plans[3]['color'],
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
