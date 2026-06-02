<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\Supplier;
use App\Models\SupplierClasificacion;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

class SupplierClasificationChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = [
        'default' => 1,
    ];

    protected ?string $heading = 'Proveedores por clasificación';

    protected ?string $description = 'Totales según la clasificación del proveedor (respeta filtros de la tabla).';

    // protected ?string $maxHeight = 'min(72vh, 900px)';

    protected string $color = 'gray';

    /**
     * @return array{fill: string, stroke: string}
     */
    private function glassColorAt(int $index): array
    {
        $palette = [
            ['fill' => 'rgba(48, 209, 88, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.78)'],
            ['fill' => 'rgba(10, 132, 255, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.78)'],
            ['fill' => 'rgba(255, 159, 10, 0.8)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(191, 90, 242, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(255, 69, 58, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(100, 210, 255, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.76)'],
            ['fill' => 'rgba(255, 214, 10, 0.78)', 'stroke' => 'rgba(255, 255, 255, 0.74)'],
            ['fill' => 'rgba(94, 92, 230, 0.76)', 'stroke' => 'rgba(255, 255, 255, 0.72)'],
        ];

        return $palette[$index % count($palette)];
    }

    private function brighterGlassFill(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $m)) {
            $a = min(0.88, (float) $m[4] + 0.18);

            return "rgba({$m[1]}, {$m[2]}, {$m[3]}, {$a})";
        }

        return $rgba;
    }

    protected function getTablePage(): string
    {
        return ListSuppliers::class;
    }

    protected function getData(): array
    {
        $table = (new Supplier)->getTable();
        $base = $this->getPageTableQuery();

        $filteredIdsQuery = (clone $base)
            ->reorder()
            ->select("{$table}.id")
            ->distinct();

        $aggregates = Supplier::query()
            ->whereIn('id', $filteredIdsQuery)
            ->selectRaw('supplier_clasificacion_id, COUNT(*) as cnt')
            ->groupBy('supplier_clasificacion_id')
            ->get();
        

        if ($aggregates->isEmpty()) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [
                    [
                        'label' => '—',
                        'data' => [0],
                        'backgroundColor' => 'rgba(142, 142, 147, 0.25)',
                        'borderColor' => 'rgba(255, 255, 255, 0.35)',
                        'borderWidth' => 1,
                        'borderRadius' => 10,
                        'borderSkipped' => false,
                    ],
                ],
            ];
        }

        $knownIds = $aggregates->pluck('supplier_clasificacion_id')->filter()->unique()->values();
        /** @var array<int|string, string> $names */
        $names = SupplierClasificacion::query()
            ->whereIn('id', $knownIds)
            ->pluck('description', 'id')
            ->all();

        $rows = [];
        foreach ($aggregates as $row) {
            $id = $row->supplier_clasificacion_id;
            if ($id === null) {
                $label = 'Sin clasificación';
            } else {
                $label = $names[$id] ?? ('Clasificación #'.$id);
            }
            $rows[] = [
                'label' => Str::limit((string) $label, 42),
                'count' => (int) $row->cnt,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $labels = array_column($rows, 'label');
        $data = array_column($rows, 'count');

        $fills = [];
        $strokes = [];
        $hovers = [];
        foreach (array_keys($data) as $i) {
            $c = $this->glassColorAt((int) $i);
            $fills[] = $c['fill'];
            $strokes[] = $c['stroke'];
            $hovers[] = $this->brighterGlassFill($c['fill']);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Proveedores',
                    'data' => $data,
                    'backgroundColor' => $fills,
                    'borderColor' => $strokes,
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $hovers,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        $iosFont = '-apple-system, BlinkMacSystemFont, system-ui, sans-serif';

        return [
            'indexAxis' => 'y',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'nearest',
                'intersect' => true,
                'axis' => 'xy',
            ],
            'datasets' => [
                'bar' => [
                    'categoryPercentage' => 0.92,
                    'barPercentage' => 0.98,
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderWidth' => 1.25,
                    'borderRadius' => 10,
                    'inflateAmount' => 0.6,
                    'hoverBorderWidth' => 2.5,
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.92)',
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                    'position' => 'nearest',
                    'xAlign' => 'center',
                    'yAlign' => 'bottom',
                    'backgroundColor' => 'rgba(22, 22, 24, 0.56)',
                    'titleColor' => '#f5f5f7',
                    'bodyColor' => 'rgba(235, 235, 245, 0.88)',
                    'footerColor' => 'rgba(235, 235, 245, 0.7)',
                    'borderColor' => 'rgba(255, 255, 255, 0.2)',
                    'borderWidth' => 1,
                    'padding' => 10,
                    'cornerRadius' => 12,
                    'caretSize' => 6,
                    'caretPadding' => 8,
                    'titleFont' => [
                        'size' => 14,
                        'weight' => '700',
                        'family' => $iosFont,
                    ],
                    'bodyFont' => [
                        'size' => 13,
                        'weight' => '500',
                        'family' => $iosFont,
                    ],
                    'titleSpacing' => 0,
                    'titleMarginBottom' => 8,
                    'bodySpacing' => 6,
                    'footerSpacing' => 8,
                    'displayColors' => true,
                    'usePointStyle' => true,
                    'boxWidth' => 12,
                    'boxHeight' => 12,
                    'boxPadding' => 8,
                    'multiKeyBackground' => 'rgba(255, 255, 255, 0.08)',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                        'drawBorder' => false,
                        'color' => 'rgba(120, 120, 128, 0.12)',
                    ],
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                        'color' => '#8e8e93',
                        'font' => [
                            'size' => 10,
                            'family' => $iosFont,
                        ],
                    ],
                ],
                'y' => [
                    'stacked' => true,
                    'grid' => [
                        'display' => true,
                        'drawBorder' => false,
                        'color' => 'rgba(120, 120, 128, 0.1)',
                    ],
                    'ticks' => [
                        'autoSkip' => false,
                        'color' => '#8e8e93',
                        'font' => [
                            'size' => 10,
                            'family' => $iosFont,
                        ],
                    ],
                ],
            ],
            'animation' => [
                'duration' => 900,
                'easing' => 'easeOutQuart',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
