<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Widgets;

use App\Filament\Business\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\State;
use App\Models\TravelAgency;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

/**
 * Agencias de viaje por estado: barras horizontales ordenadas de mayor a menor.
 *
 * Antes era una torta: con una docena de estados y uno solo concentrando casi
 * la mitad, las porciones pequeñas no se distinguían y la leyenda no se leía en
 * modo oscuro. Comparar cantidades es trabajo de barras: un solo color (el
 * estado no necesita color propio, su nombre ya lo identifica), el número y el
 * porcentaje escritos junto a cada estado, y «Sin estado» en gris al final
 * porque no es un estado sino un dato por completar.
 */
class TravelAgencyForStateChart extends ChartWidget
{
    use InteractsWithPageTable;

    public const WITHOUT_STATE = 'Sin estado';

    /** Azul de magnitud validado para superficie clara y oscura (contraste ≥ 3:1). */
    private const BAR_LIGHT = '#2a78d6';

    private const BAR_DARK = '#3987e5';

    /** Gris de menor énfasis para «Sin estado». */
    private const MUTED = '#8a8983';

    /** Alto objetivo por barra y ancho de referencia del panel, para la proporción del gráfico. */
    private const ROW_HEIGHT = 34;

    private const REFERENCE_WIDTH = 1300;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Agencias de viaje por estado';

    protected ?string $maxHeight = '720px';

    protected string $color = 'gray';

    /**
     * @var array{rows: list<array{label: string, count: int, pct: float, muted: bool}>, total: int}|null
     */
    private ?array $summary = null;

    protected function getTablePage(): string
    {
        return ListTravelAgencies::class;
    }

    public function getDescription(): ?string
    {
        $summary = $this->summary();

        if ($summary['total'] === 0) {
            return 'No hay agencias con la búsqueda y los filtros actuales.';
        }

        $states = array_values(array_filter($summary['rows'], static fn (array $row): bool => ! $row['muted']));
        $leader = $states[0] ?? null;
        $parts = [
            $summary['total'].' '.($summary['total'] === 1 ? 'agencia' : 'agencias').' en '.count($states).' '.(count($states) === 1 ? 'estado' : 'estados').'.',
        ];

        if ($leader !== null && count($states) > 1) {
            $parts[] = $leader['label'].' concentra el '.self::percent($leader['pct']).' ('.$leader['count'].').';
        }

        $parts[] = 'De mayor a menor; respeta la búsqueda y los filtros del listado.';

        return implode(' ', $parts);
    }

    protected function getData(): array
    {
        $summary = $this->summary();

        if ($summary['total'] === 0) {
            return [
                'labels' => ['Sin agencias'],
                'datasets' => [[
                    'label' => 'Agencias de viaje',
                    'data' => [0],
                    'counts' => [0],
                    'percentages' => [0],
                    'backgroundColor' => self::MUTED,
                ]],
            ];
        }

        $dark = self::BAR_DARK;
        $light = self::BAR_LIGHT;

        return [
            'labels' => array_map(static fn (array $row): string => $row['label'], $summary['rows']),
            'datasets' => [[
                'label' => 'Agencias de viaje',
                'data' => array_map(static fn (array $row): int => $row['count'], $summary['rows']),
                'counts' => array_map(static fn (array $row): int => $row['count'], $summary['rows']),
                'percentages' => array_map(static fn (array $row): float => $row['pct'], $summary['rows']),
                'muted' => array_map(static fn (array $row): bool => $row['muted'], $summary['rows']),
                /** El color exacto se resuelve en el navegador según el tema (ver getOptions). */
                'colorLight' => $light,
                'colorDark' => $dark,
                'colorMuted' => self::MUTED,
                'borderWidth' => 0,
                'borderRadius' => ['topRight' => 4, 'bottomRight' => 4, 'topLeft' => 0, 'bottomLeft' => 0],
                'borderSkipped' => false,
                'barPercentage' => 0.78,
                'categoryPercentage' => 0.88,
                'maxBarThickness' => 26,
            ]],
        ];
    }

    protected function getOptions(): RawJs
    {
        $rows = max(1, count($this->summary()['rows']));
        /** Alto deseado según la cantidad de estados, convertido en proporción ancho/alto. */
        $desiredHeight = $rows * self::ROW_HEIGHT + 64;
        $aspectRatio = round(max(1.6, min(7.0, self::REFERENCE_WIDTH / $desiredHeight)), 2);

        return RawJs::make(<<<JS
        {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: window.innerWidth < 768 ? Math.max(0.8, {$aspectRatio} / 3) : {$aspectRatio},
            indexAxis: 'y',
            animation: { duration: 500, easing: 'easeOutQuart' },
            layout: { padding: { top: 4, right: 16, bottom: 0, left: 0 } },
            datasets: {
                bar: {
                    backgroundColor: (context) => {
                        const ds = context.dataset;
                        const isDark = document.documentElement.classList.contains('dark');

                        if (Array.isArray(ds.muted) && ds.muted[context.dataIndex]) {
                            return ds.colorMuted;
                        }

                        return isDark ? ds.colorDark : ds.colorLight;
                    },
                    hoverBackgroundColor: (context) => {
                        const ds = context.dataset;
                        const isDark = document.documentElement.classList.contains('dark');

                        if (Array.isArray(ds.muted) && ds.muted[context.dataIndex]) {
                            return isDark ? '#a3a29c' : '#6f6e69';
                        }

                        return isDark ? '#5b9ff0' : '#1f63b8';
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grace: '8%',
                    position: 'top',
                    grid: {
                        display: true,
                        drawTicks: false,
                        color: () => document.documentElement.classList.contains('dark') ? 'rgba(255, 255, 255, 0.07)' : 'rgba(15, 23, 42, 0.07)',
                    },
                    border: { display: false },
                    ticks: {
                        precision: 0,
                        padding: 6,
                        color: () => document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280',
                        font: { size: 11 },
                    },
                    title: {
                        display: true,
                        text: 'Cantidad de agencias',
                        color: () => document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280',
                        font: { size: 11, weight: '600' },
                        padding: { bottom: 4 },
                    },
                },
                y: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        autoSkip: false,
                        padding: 10,
                        color: () => document.documentElement.classList.contains('dark') ? '#f3f4f6' : '#111827',
                        font: { size: 12.5, weight: '600' },
                        /** Nombre, cantidad y porcentaje escritos junto a la barra: no hace falta leyenda. */
                        callback: function (value) {
                            const ds = this.chart.data.datasets[0];
                            const label = this.getLabelForValue(value);
                            const count = ds.counts ? ds.counts[value] : null;
                            const pct = ds.percentages ? ds.percentages[value] : null;

                            if (count === null || count === undefined) {
                                return label;
                            }

                            return label + '   ' + count + ' · ' + String(pct).replace('.', ',') + ' %';
                        },
                    },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    padding: 12,
                    cornerRadius: 10,
                    backgroundColor: () => document.documentElement.classList.contains('dark') ? 'rgba(24, 24, 27, 0.96)' : 'rgba(255, 255, 255, 0.98)',
                    borderColor: () => document.documentElement.classList.contains('dark') ? 'rgba(255, 255, 255, 0.12)' : 'rgba(15, 23, 42, 0.12)',
                    borderWidth: 1,
                    titleColor: () => document.documentElement.classList.contains('dark') ? '#f9fafb' : '#0f172a',
                    bodyColor: () => document.documentElement.classList.contains('dark') ? '#d1d5db' : '#334155',
                    titleFont: { size: 13, weight: '700' },
                    bodyFont: { size: 12 },
                    callbacks: {
                        label: (context) => {
                            const ds = context.dataset;
                            const count = ds.counts[context.dataIndex];
                            const pct = String(ds.percentages[context.dataIndex]).replace('.', ',');
                            const total = ds.counts.reduce((sum, value) => sum + value, 0);

                            return [
                                count + (count === 1 ? ' agencia' : ' agencias') + ' (' + pct + ' % del total)',
                                'Total en el listado: ' + total,
                            ];
                        },
                    },
                },
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
            },
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Conteo por estado sobre la misma consulta del listado (búsqueda y filtros),
     * de mayor a menor, con «Sin estado» siempre al final. Se calcula una vez
     * por render: lo usan los datos, la descripción y la proporción del gráfico.
     *
     * @return array{rows: list<array{label: string, count: int, pct: float, muted: bool}>, total: int}
     */
    private function summary(): array
    {
        if ($this->summary !== null) {
            return $this->summary;
        }

        $table = (new TravelAgency)->getTable();
        $filteredIds = $this->getPageTableQuery()->reorder()->select("{$table}.id")->distinct();

        $counts = TravelAgency::query()
            ->whereIn('id', $filteredIds)
            ->selectRaw('state_id, COUNT(*) as total')
            ->groupBy('state_id')
            ->pluck('total', 'state_id');

        $withoutState = (int) ($counts[''] ?? 0);
        $stateCounts = $counts->filter(static fn (mixed $total, mixed $stateId): bool => $stateId !== '' && $stateId !== null);
        $names = State::query()->whereIn('id', $stateCounts->keys())->pluck('definition', 'id');
        $total = (int) $counts->sum();

        $rows = [];

        foreach ($stateCounts as $stateId => $count) {
            $rows[] = [
                'label' => Str::limit((string) ($names[$stateId] ?? 'Estado #'.$stateId), 30),
                'count' => (int) $count,
                'pct' => $total > 0 ? round(((int) $count / $total) * 100, 1) : 0.0,
                'muted' => false,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['count'], $a['label']] <=> [$a['count'], $b['label']]);

        if ($withoutState > 0) {
            $rows[] = [
                'label' => self::WITHOUT_STATE,
                'count' => $withoutState,
                'pct' => $total > 0 ? round(($withoutState / $total) * 100, 1) : 0.0,
                'muted' => true,
            ];
        }

        return $this->summary = ['rows' => $rows, 'total' => $total];
    }

    private static function percent(float $value): string
    {
        return str_replace('.', ',', (string) $value).' %';
    }
}
