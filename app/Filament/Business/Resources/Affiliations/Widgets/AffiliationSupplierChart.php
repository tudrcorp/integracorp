<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class AffiliationSupplierChart extends ChartWidget
{
    use InteractsWithPageTable;

    private const SUPPLIER_LABELS = [
        'ATENMEDI' => '#9ce1ff',
        'ILS' => '#25b4e7',
        'TDEC' => '#2d89ca',
    ];

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

    protected ?string $heading = 'DISTRIBUCIÓN DE AFILIACIONES POR AFILIADOR';

    protected ?string $description = 'Análisis porcentual y cuantitativo de afiliaciones por proveedor en el mes actual.';

    // Ocupar medio ancho para que se vea mejor en el dashboard junto a otros widgets
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $stats = $this->getPageTableQuery()
            ->reorder()
            ->selectRaw("
                SUM(CASE WHEN service_providers LIKE '%ATENMEDI%' THEN 1 ELSE 0 END) as atenmedi_count,
                SUM(CASE WHEN service_providers LIKE '%ILS%' THEN 1 ELSE 0 END) as ils_count,
                SUM(CASE WHEN service_providers LIKE '%TDEC%' THEN 1 ELSE 0 END) as tdec_count
            ")
            ->first();

        $counts = [
            'ATENMEDI' => (int) ($stats->atenmedi_count ?? 0),
            'ILS' => (int) ($stats->ils_count ?? 0),
            'TDEC' => (int) ($stats->tdec_count ?? 0),
        ];

        $totalCountSuppliers = array_sum($counts);

        // Evitar división por cero
        if ($totalCountSuppliers === 0) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [['data' => [0], 'backgroundColor' => ['#e5e7eb']]],
            ];
        }

        $data = [
            round(($counts['ATENMEDI'] * 100) / $totalCountSuppliers),
            round(($counts['ILS'] * 100) / $totalCountSuppliers),
            round(($counts['TDEC'] * 100) / $totalCountSuppliers),
        ];

        return [
            'labels' => array_keys(self::SUPPLIER_LABELS),
            'datasets' => [
                [
                    'label' => 'Porcentaje',
                    'data' => $data,
                    'backgroundColor' => array_values(self::SUPPLIER_LABELS),
                    // Efectos de interacción mejorados
                    'hoverOffset' => 30,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 3,
                    'hoverBorderColor' => '#ffffff',
                    'hoverBorderWidth' => 5,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
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
                        textShadowColor: 'rgba(0, 0, 0, 0.5)',
                        textShadowBlur: 4
                    }
                },
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
