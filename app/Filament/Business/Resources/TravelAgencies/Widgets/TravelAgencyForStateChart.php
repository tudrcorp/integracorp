<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Filament\Business\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\State;
use App\Models\TravelAgency;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

class TravelAgencyForStateChart extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use InteractsWithPageTable;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Agencias de viaje por estado';

    protected ?string $description = 'Totales por estado (respeta búsqueda y filtros del listado).';

    protected ?string $maxHeight = '440px';

    protected string $color = 'gray';

    protected function getTablePage(): string
    {
        return ListTravelAgencies::class;
    }

    protected function getData(): array
    {
        $table = (new TravelAgency)->getTable();
        $base = $this->getPageTableQuery();

        $distinctStateIds = (clone $base)
            ->reorder()
            ->whereNotNull("{$table}.state_id")
            ->distinct()
            ->pluck("{$table}.state_id");

        $stateRows = State::query()
            ->whereIn('id', $distinctStateIds)
            ->orderBy('definition')
            ->get();

        $hasWithoutState = (clone $base)->reorder()->whereNull("{$table}.state_id")->exists();

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

        $aggregates = TravelAgency::query()
            ->whereIn('id', $filteredIdsQuery)
            ->selectRaw('state_id, COUNT(*) as cnt')
            ->groupBy('state_id')
            ->get();

        /** @var array<string, int> $countMap */
        $countMap = [];
        foreach ($aggregates as $row) {
            $key = $row->state_id === null ? 'null_state' : (string) $row->state_id;
            $countMap[$key] = (int) $row->cnt;
        }

        $data = [];
        foreach ($stateRows as $state) {
            $data[] = $countMap[(string) $state->id] ?? 0;
        }
        if ($hasWithoutState) {
            $data[] = $countMap['null_state'] ?? 0;
        }

        $colors = $this->glassBarColorsForValues($data);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Agencias',
                    'data' => $data,
                    'backgroundColor' => $colors['fills'],
                    'borderColor' => $colors['strokes'],
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $colors['hovers'],
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
