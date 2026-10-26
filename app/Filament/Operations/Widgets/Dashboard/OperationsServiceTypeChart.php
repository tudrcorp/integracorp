<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use App\Support\Operations\Dashboard\OperationsDashboardChartJs as Js;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Servicios por tipo, de mayor a menor, en dos series: los servicios
 * principales (macro: telemedicina, AMD, traslados…, uno por consulta) y los
 * derivados que se indicaron en ellas (medicamentos, laboratorios…). Cuentan
 * cosas distintas, por eso van en colores distintos y con leyenda.
 *
 * Las subclases (dashboard del proveedor, panel corporativo) devuelven null en
 * {@see self::typeBreakdown()} y se quedan con una sola serie de un solo color.
 */
class OperationsServiceTypeChart extends ChartWidget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected string $view = 'filament.operations.dashboard.chart';

    protected ?string $maxHeight = '420px';

    /** Altura por categoría: así caben todas las etiquetas sin que Chart.js salte ninguna. */
    public const ROW_HEIGHT = 34;

    public const PRINCIPAL_LABEL = 'Servicio principal';

    public const DERIVED_LABEL = 'Servicio derivado';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Servicios por tipo';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Servicios principales (uno por consulta) y los derivados que se indicaron en ellas. Haga clic en una barra para ver el detalle.';
    }

    /**
     * Servicios principales y derivados, o null para una sola serie.
     *
     * @return array{principal: array<string, int>, derived: array<string, int>}|null
     */
    protected function typeBreakdown(): ?array
    {
        return OperationsDashboardAnalytics::serviceTypeBreakdown($this->period());
    }

    /**
     * Conteo por tipo, de mayor a menor. El del proveedor cuenta casos.
     *
     * @return array<string, int>
     */
    protected function typeCounts(): array
    {
        $breakdown = $this->typeBreakdown();

        if ($breakdown === null) {
            return OperationsDashboardAnalytics::byServiceType($this->period());
        }

        return $breakdown['principal'] + $breakdown['derived'];
    }

    protected function noun(): string
    {
        return 'servicios';
    }

    /**
     * Si los valores son dinero (USD).
     */
    protected function isMoney(): bool
    {
        return false;
    }

    /**
     * Texto bajo la cifra en el tooltip (ej.: «% del total»).
     */
    protected function shareText(): string
    {
        return ' del total';
    }

    protected function detailDimension(): string
    {
        return 'service_type';
    }

    /**
     * Crece con la cantidad de tipos (mínimo 180 px, máximo la altura del widget).
     */
    public function getChartHeight(): string
    {
        $rows = count($this->typeCounts());

        $legend = $this->typeBreakdown() !== null ? 28 : 0;

        return min(420, max(180, $rows * self::ROW_HEIGHT + 40 + $legend)).'px';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $breakdown = $this->typeBreakdown();

        if ($breakdown === null) {
            $counts = $this->typeCounts();

            return [
                'datasets' => [self::dataset('Servicios', array_values($counts))],
                'labels' => array_keys($counts),
            ];
        }

        /*
         * Una serie por grupo sobre las mismas categorías, con null donde la
         * categoría es del otro grupo. Los ejes van apilados: cada barra queda
         * centrada en su fila como si fuera una sola serie.
         */
        $labels = array_merge(array_keys($breakdown['principal']), array_keys($breakdown['derived']));
        $principal = array_map(static fn (string $label): ?int => $breakdown['principal'][$label] ?? null, $labels);
        $derived = array_map(static fn (string $label): ?int => array_key_exists($label, $breakdown['principal']) ? null : ($breakdown['derived'][$label] ?? null), $labels);

        return [
            'datasets' => [
                self::dataset(self::PRINCIPAL_LABEL, $principal),
                self::dataset(self::DERIVED_LABEL, $derived),
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @param  list<int|null>  $data
     * @return array<string, mixed>
     */
    private static function dataset(string $label, array $data): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderRadius' => 4,
            'borderSkipped' => 'start',
            'maxBarThickness' => 22,
            'categoryPercentage' => 0.78,
            'barPercentage' => 0.9,
        ];
    }

    protected function getOptions(): RawJs
    {
        if ($this->typeBreakdown() !== null) {
            return $this->splitOptions();
        }

        $money = $this->isMoney();
        $plural = Js::jsString($money ? '' : $this->noun());
        $singular = Js::jsString($money ? '' : Js::singular($this->noun()));
        $footer = $this->detailDimension() !== '' ? Js::jsString('Clic para ver el detalle') : 'null';
        $format = $money ? 'money' : 'fmt';
        $tick = $money ? 'moneyShort' : 'fmt';
        $share = Js::jsString($this->shareText());

        $tooltip = Js::tooltip(<<<JS
            (tooltip, chart) => {
                const point = tooltip.dataPoints[0];
                const data = point.dataset.data;
                const total = data.reduce((sum, value) => sum + value, 0);
                return {
                    title: point.label,
                    rows: [{ swatch: ops.series1, value: {$format}(point.parsed.x), unit: point.parsed.x === 1 ? {$singular} : {$plural} }],
                    lines: [pct(point.parsed.x, total) + {$share} + ' · puesto ' + (point.dataIndex + 1) + ' de ' + data.length],
                    footer: {$footer},
                };
            }
        JS);

        $click = $this->detailDimension() !== '' ? Js::onClickDetail($this->detailDimension()) : '';
        $x = Js::axis('beginAtZero: true,', ticks: 'precision: 0, callback: (v) => '.$tick.'(v),');
        $y = Js::axis(grid: false, ticks: "autoSkip: false, font: { size: 11, weight: '500' }, callback: function (value) { const label = this.getLabelForValue(value); return label.length > 26 ? label.slice(0, 25) + '…' : label; },");

        return Js::options(<<<JS
            indexAxis: 'y',
            {$click}
            datasets: { bar: { backgroundColor: ops.series1, hoverBackgroundColor: ops.series1 + 'cc' } },
            plugins: { legend: { display: false }, {$tooltip} },
            scales: { x: {$x}, y: {$y} },
        JS);
    }

    /**
     * Dos series: principal en el color 1 y derivado en el 2, leyenda arriba, el
     * porcentaje y el puesto dentro de su propio grupo (sumar consultas con
     * medicamentos no daría un total con sentido) y el clic abre el detalle de
     * la dimensión que le toca a cada serie.
     */
    private function splitOptions(): RawJs
    {
        $tooltip = Js::tooltip(<<<'JS'
            (tooltip, chart) => {
                const point = tooltip.dataPoints[0];
                const values = point.dataset.data.filter((value) => value !== null);
                const total = values.reduce((sum, value) => sum + value, 0);
                const rank = values.filter((value) => value > point.parsed.x).length + 1;
                const principal = point.datasetIndex === 0;
                return {
                    title: point.label,
                    rows: [{ swatch: principal ? ops.series1 : ops.series2, value: fmt(point.parsed.x), unit: point.parsed.x === 1 ? 'servicio' : 'servicios' }],
                    lines: [
                        principal ? 'Servicio principal (uno por consulta)' : 'Servicio derivado (indicado en una consulta)',
                        pct(point.parsed.x, total) + (principal ? ' de los principales' : ' de los derivados') + ' · puesto ' + rank + ' de ' + values.length,
                    ],
                    footer: 'Clic para ver el detalle',
                };
            }
        JS);

        $x = Js::axis('beginAtZero: true, stacked: true,', ticks: 'precision: 0, callback: (v) => fmt(v),');
        $y = Js::axis('stacked: true,', grid: false, ticks: "autoSkip: false, font: { size: 11, weight: '500' }, callback: function (value) { const label = this.getLabelForValue(value); return label.length > 26 ? label.slice(0, 25) + '…' : label; },");

        return Js::options(<<<JS
            indexAxis: 'y',
            onClick: (event, elements, chart) => {
                if (!elements || !elements.length) { return; }
                const label = chart.data.labels[elements[0].index];
                \$wire.dispatch('operations-dashboard-detail', { dimension: elements[0].datasetIndex === 0 ? 'macro_service' : 'service_type', value: String(label) });
            },
            onHover: (event, elements) => {
                if (event?.native?.target) { event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; }
            },
            datasets: { bar: {
                skipNull: true,
                backgroundColor: (context) => context.datasetIndex === 0 ? ops.series1 : ops.series2,
                hoverBackgroundColor: (context) => (context.datasetIndex === 0 ? ops.series1 : ops.series2) + 'cc',
            } },
            plugins: {
                legend: { display: true, position: 'top', align: 'end', labels: { color: ops.text, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16 } },
                {$tooltip}
            },
            scales: { x: {$x}, y: {$y} },
        JS);
    }

    /**
     * Solo para tests: las opciones tal como viajan al navegador.
     */
    public function getOptionsForTest(): string
    {
        return $this->getOptions()->toHtml();
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
