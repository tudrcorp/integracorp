<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AffiliationAgencyChart extends ChartWidget
{
    public function mount(): void
    {
        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);
    }

    protected ?string $maxHeight = '320px';

    protected ?string $heading = 'DISTRIBUCIÓN POR AGENCIA';

    protected ?string $description = 'Análisis porcentual de afiliaciones activas por agencia en el mes actual.';

    protected int | string | array $columnSpan = 'full';

    /**
     * Genera una paleta de colores pasteles cálidos aleatorios.
     */
    protected function generateWarmPastelColors(int $count): array
    {
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            // H: 0-60 (Rojos, Naranjas, Amarillos) | S: 60-80% | L: 75-85% (Pastel)
            $hue = rand(0, 50);
            $saturation = rand(65, 85);
            $lightness = rand(75, 85);
            $colors[] = "hsl({$hue}, {$saturation}%, {$lightness}%)";
        }
        return $colors;
    }

    protected function getData(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // QUERY OPTIMIZADO: 
        // 1. Unimos con la tabla 'agencies' usando el campo común (asumiendo 'code_agency')
        // 2. Seleccionamos el nombre corporativo y el conteo
        $results = DB::table('affiliations')
            ->leftJoin('agencies', 'affiliations.code_agency', '=', 'agencies.code')
            ->select(
                DB::raw('COALESCE(agencies.name_corporative, affiliations.code_agency) as agency_name'),
                DB::raw('count(*) as total')
            )
            ->whereBetween('affiliations.created_at', [$startOfMonth, $endOfMonth])
            ->where('affiliations.status', 'ACTIVA')
            ->groupBy('agency_name')
            ->orderByDesc('total')
            ->get();

        if ($results->isEmpty()) {
            return [
                'labels' => ['Sin registros'],
                'datasets' => [[
                    'data' => [100],
                    'backgroundColor' => ['#f3f4f6'],
                ]]
            ];
        }

        $totalGlobal = $results->sum('total');

        $labels = $results->pluck('agency_name')->toArray();
        $dataCounts = $results->pluck('total')->toArray();

        // Calculamos porcentajes para los datalabels
        $percentages = $results->map(fn($item) => round(($item->total / $totalGlobal) * 100, 1))->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $dataCounts,
                    'percentages' => $percentages, // Pasamos esto para el tooltip/datalabel
                    'backgroundColor' => $this->generateWarmPastelColors(count($labels)),
                    'hoverOffset' => 25,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<JS
            {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1200
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 11, weight: '500' }
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let percentage = context.dataset.percentages[context.dataIndex];
                                return ` \${label}: \${value} (\${percentage}%)`;
                            }
                        }
                    },
                    datalabels: {
                        display: function(context) {
                            // Solo mostrar si el porcentaje es mayor al 5% para evitar amontonamiento
                            return context.dataset.percentages[context.dataIndex] > 5;
                        },
                        color: '#444',
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: function(value, context) {
                            return context.dataset.percentages[context.dataIndex] + '%';
                        }
                    }
                },
                layout: {
                    padding: { top: 10, bottom: 10, left: 10, right: 10 }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
