<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\State;
use App\Models\Supplier;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Str;

class SupplierForState extends ChartWidget
{
    use InteractsWithPageTable;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = [
        'default' => 1,
    ];

    protected ?string $heading = 'Proveedores por estado y estatus en sistema';

    protected ?string $description = 'Totales por estado y estatus en sistema (respeta filtros de la tabla).';

    // protected ?string $maxHeight = '350px';

    protected string $color = 'gray';

    /**
     * Orden preferido para leyenda y apilado (izquierda → derecha en barras horizontales).
     *
     * @var list<string>
     */
    private const STATUS_PRIORITY = [
        'AFILIADO',
        'ACTIVO AFILIADO',
        'EN PROCESO',
        'ACTIVO EN PROCESO',
        'SIN RESPUESTA',
        'NO DESEA CONVENIO',
        'CONVENIO SUSPENDIDO POR EL PROVEEDOR',
        'CONVENIO SUSPENDIDO POR TDEC',
    ];

    private function normalizeSistemaStatusForMatch(string $label): string
    {
        $s = trim($label);
        $s = Str::ascii($s);
        $s = preg_replace('/^[\d\.\)\-]+\s*/', '', $s) ?? $s;
        $s = str_replace(['–', '—', '-', '/', '|', '_', ',', ';', ':'], ' ', $s);
        $s = preg_replace('/[\x{00A0}\h]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', trim($s)) ?? $s;

        return mb_strtoupper($s, 'UTF-8');
    }

    /**
     * Palabras consecutivas permitiendo separadores variables (espacios, guiones, etc.).
     */
    private function statusLabelMatchesWordSequence(string $normalizedLabel, string $phrase): bool
    {
        $words = preg_split('/\s+/', trim($phrase), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            return false;
        }

        $parts = array_map(
            static fn (string $w): string => preg_quote($w, '/'),
            $words,
        );
        $pattern = '/'.implode('\s*', $parts).'/u';

        return (bool) preg_match($pattern, $normalizedLabel);
    }

    /**
     * @return array{fill: string, stroke: string}
     */
    private function unknownSistemaStatusGlassColors(): array
    {
        return [
            'fill' => 'rgba(120, 120, 128, 0.48)',
            'stroke' => 'rgba(255, 255, 255, 0.65)',
        ];
    }

    /**
     * Colores por estatus en sistema (`status_sistema`), orden de reglas: más específico primero.
     *
     * @return array{fill: string, stroke: string}
     */
    private function glassColorsForSistemaStatus(string $statusLabel): array
    {
        $n = $this->normalizeSistemaStatusForMatch($statusLabel);

        if ($n === '' || str_contains($n, 'SIN ESTATUS EN SISTEMA')) {
            return [
                'fill' => 'rgba(142, 142, 147, 0.42)',
                'stroke' => 'rgba(255, 255, 255, 0.72)',
            ];
        }

        if (
            str_contains($n, 'TDEC')
            && (preg_match('/SUSPENDID/u', $n) || $this->statusLabelMatchesWordSequence($n, 'SUSPENDIDO POR TDEC'))
        ) {
            return [
                'fill' => 'rgba(130, 0, 0, 0.9)',
                'stroke' => 'rgba(255, 255, 255, 0.65)',
            ];
        }

        if (
            str_contains($n, 'PROVEEDOR')
            && preg_match('/SUSPENDID/u', $n)
            && ! str_contains($n, 'TDEC')
        ) {
            return [
                'fill' => 'rgba(154, 0, 0, 0.85)',
                'stroke' => 'rgba(255, 255, 255, 0.78)',
            ];
        }

        if (
            $this->statusLabelMatchesWordSequence($n, 'ACTIVO AFILIADO')
            || $this->statusLabelMatchesWordSequence($n, 'AFILIADO ACTIVO')
        ) {
            return [
                'fill' => 'rgba(39, 98, 33, 0.82)',
                'stroke' => 'rgba(255, 255, 255, 0.78)',
            ];
        }

        if ($this->statusLabelMatchesWordSequence($n, 'ACTIVO EN PROCESO')) {
            return [
                'fill' => 'rgba(220, 102, 1, 0.82)',
                'stroke' => 'rgba(255, 255, 255, 0.78)',
            ];
        }

        if ($this->statusLabelMatchesWordSequence($n, 'EN PROCESO')) {
            return [
                'fill' => 'rgba(238, 159, 39, 0.82)',
                'stroke' => 'rgba(255, 255, 255, 0.78)',
            ];
        }

        if ($this->statusLabelMatchesWordSequence($n, 'NO DESEA CONVENIO')) {
            return [
                'fill' => 'rgba(179, 0, 0, 0.82)',
                'stroke' => 'rgba(255, 255, 255, 0.78)',
            ];
        }

        if ($this->statusLabelMatchesWordSequence($n, 'SIN RESPUESTA')) {
            return [
                'fill' => 'rgba(205, 0, 0, 0.82)',
                'stroke' => 'rgba(255, 255, 255, 0.78)',
            ];
        }

        if (preg_match('/\bAFILIADO\b/u', $n) === 1) {
            return [
                'fill' => 'rgba(70, 146, 60, 0.82)',
                'stroke' => 'rgba(255, 255, 255, 0.78)',
            ];
        }

        return $this->unknownSistemaStatusGlassColors();
    }

    protected function getTablePage(): string
    {
        return ListSuppliers::class;
    }

    protected function getData(): array
    {
        $table = (new Supplier)->getTable();
        $base = $this->getPageTableQuery();

        $stateRows = State::query()
            ->whereIn('id', (clone $base)->whereNotNull("{$table}.state_id")->distinct()->pluck("{$table}.state_id"))
            ->orderBy('definition')
            ->get();

        $hasWithoutState = (clone $base)->whereNull("{$table}.state_id")->exists();

        $labels = $stateRows
            ->map(fn (State $state): string => Str::limit($state->definition, 22))
            ->values()
            ->all();

        if ($hasWithoutState) {
            $labels[] = 'Sin estado';
        }

        if ($labels === []) {
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

        $filteredIdsQuery = (clone $base)
            ->reorder()
            ->select("{$table}.id")
            ->distinct();

        $aggregates = Supplier::query()
            ->whereIn('id', $filteredIdsQuery)
            ->selectRaw('state_id, status_sistema, COUNT(*) as cnt')
            ->groupBy('state_id', 'status_sistema')
            ->get();

        /** @var array<string, array<string, int>> $countMap */
        $countMap = [];
        foreach ($aggregates as $row) {
            $stateKey = $row->state_id === null ? 'null_state' : (string) $row->state_id;
            $statusKey = $row->status_sistema === null ? '__null__' : (string) $row->status_sistema;
            $countMap[$stateKey][$statusKey] = (int) $row->cnt;
        }

        $distinctStatuses = (clone $base)
            ->whereNotNull("{$table}.status_sistema")
            ->distinct()
            ->pluck("{$table}.status_sistema")
            ->values();

        $orderedStatuses = collect(self::STATUS_PRIORITY)
            ->merge($distinctStatuses)
            ->unique()
            ->values()
            ->all();

        $hasNullStatus = (clone $base)->whereNull("{$table}.status_sistema")->exists();

        $datasets = [];

        foreach ($orderedStatuses as $status) {
            $data = [];
            foreach ($stateRows as $state) {
                $data[] = $countMap[(string) $state->id][$status] ?? 0;
            }
            if ($hasWithoutState) {
                $data[] = $countMap['null_state'][$status] ?? 0;
            }

            $c = $this->glassColorsForSistemaStatus($status);

            $datasets[] = [
                'label' => $status,
                'data' => $data,
                'backgroundColor' => $c['fill'],
                'borderColor' => $c['stroke'],
                'borderWidth' => 1.25,
                'borderRadius' => 8,
                'borderSkipped' => false,
                'hoverBackgroundColor' => $this->brighterGlassFill($c['fill']),
            ];
        }

        if ($hasNullStatus) {
            $data = [];
            foreach ($stateRows as $state) {
                $data[] = $countMap[(string) $state->id]['__null__'] ?? 0;
            }
            if ($hasWithoutState) {
                $data[] = $countMap['null_state']['__null__'] ?? 0;
            }

            if (array_sum($data) > 0) {
                $c = $this->glassColorsForSistemaStatus('Sin estatus en sistema');
                $datasets[] = [
                    'label' => 'Sin estatus en sistema',
                    'data' => $data,
                    'backgroundColor' => $c['fill'],
                    'borderColor' => $c['stroke'],
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $this->brighterGlassFill($c['fill']),
                ];
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function brighterGlassFill(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $m)) {
            $a = min(0.88, (float) $m[4] + 0.18);

            return "rgba({$m[1]}, {$m[2]}, {$m[3]}, {$a})";
        }

        return $rgba;
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
