<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Concerns\HasProspectResourceChartTimeStateFilters;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Models\ProspectAgent;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class StatusChangesByMonth extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use HasProspectResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Cambios de estatus por mes';

    protected ?string $description = 'Conteo de actualizaciones por estatus, agrupado por mes (según updated_at).';

    protected ?string $maxHeight = '420px';

    public function mount(): void
    {
        parent::mount();
        $this->bootProspectChartFilters();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        $start = Carbon::create($year, $month ?? 1, 1)->startOfMonth();
        $end = $month
            ? Carbon::create($year, $month, 1)->endOfMonth()
            : Carbon::create($year, 1, 1)->endOfYear();

        $rows = ProspectAgent::query()
            ->whereBetween('updated_at', [$start, $end])
            ->selectRaw('COALESCE(NULLIF(TRIM(status), \'\'), \'Sin estatus\') as status_label')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status_label')
            ->orderByDesc('total')
            ->get();

        $labels = $rows
            ->pluck('status_label')
            ->map(static fn (mixed $s): string => (string) $s)
            ->toArray();

        $values = $rows
            ->pluck('total')
            ->map(static fn (mixed $n): int => (int) $n)
            ->toArray();

        $bluePalette = [
            'rgba(0, 122, 255, 0.82)',
            'rgba(10, 132, 255, 0.82)',
            'rgba(64, 156, 255, 0.8)',
            'rgba(90, 200, 250, 0.78)',
            'rgba(52, 120, 246, 0.8)',
            'rgba(94, 92, 230, 0.78)',
        ];

        $fills = array_map(
            static fn (int $i): string => $bluePalette[$i % count($bluePalette)],
            array_keys($values)
        );
        $strokes = array_fill(0, count($values), 'rgba(255, 255, 255, 0.78)');
        $hovers = array_map(fn (string $rgba): string => $this->brighterHover($rgba), $fills);

        $labelRange = $month
            ? ucfirst(Carbon::create($year, $month, 1)->translatedFormat('F Y'))
            : (string) $year;

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => "Cambios en {$labelRange}",
                    'data' => $values,
                    'backgroundColor' => $fills,
                    'borderColor' => $strokes,
                    'borderWidth' => 1.25,
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $hovers,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return array_replace_recursive($this->agencyStyleVerticalBarChartOptions(), [
            'interaction' => [
                'mode' => 'nearest',
                'intersect' => true,
            ],
            'datasets' => [
                'bar' => [
                    'categoryPercentage' => 0.62,
                    'barPercentage' => 0.82,
                ],
            ],
            'elements' => [
                'bar' => [
                    'borderRadius' => 12,
                    'borderSkipped' => false,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'nearest',
                    'intersect' => true,
                    'callbacks' => RawJs::make(<<<'JS'
                        {
                            title: (items) => {
                                // Mes (label del eje X)
                                return items?.[0]?.label ? `Mes: ${items[0].label}` : '';
                            },
                            label: (context) => {
                                // Dataset = Estatus, value = cantidad
                                const status = context.dataset?.label ?? 'Estatus';
                                const value = context.parsed?.y ?? 0;
                                const n = Number(value) || 0;
                                const changes = n === 1 ? 'cambio' : 'cambios';
                                return `${status}: ${n} ${changes}`;
                            },
                            footer: () => {
                                return 'Qué significa: cantidad de registros cuyo estatus quedó así en el mes (según updated_at).';
                            }
                        }
                        JS),
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'color' => '#000000',
                        'font' => [
                            'size' => 13,
                        ],
                        'maxRotation' => 35,
                        'minRotation' => 0,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'color' => 'rgba(120, 120, 128, 0.10)',
                    ],
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                    ],
                ],
            ],
        ]);
    }

    private function brighterHover(string $rgba): string
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $rgba, $m)) {
            $a = min(0.9, (float) $m[4] + 0.16);

            return "rgba({$m[1]}, {$m[2]}, {$m[3]}, {$a})";
        }

        return $rgba;
    }
}
