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
 * Servicios por tipo, de mayor a menor. Una sola serie, un solo color.
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

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Servicios por tipo';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Qué se pide más. Haga clic en una barra para ver los servicios.';
    }

    /**
     * Conteo por tipo, de mayor a menor. El del proveedor cuenta casos.
     *
     * @return array<string, int>
     */
    protected function typeCounts(): array
    {
        return OperationsDashboardAnalytics::byServiceType($this->period());
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

        return min(420, max(180, $rows * self::ROW_HEIGHT + 40)).'px';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = $this->typeCounts();

        return [
            'datasets' => [[
                'label' => 'Servicios',
                'data' => array_values($counts),
                'borderRadius' => 4,
                'borderSkipped' => 'start',
                'maxBarThickness' => 22,
                'categoryPercentage' => 0.78,
                'barPercentage' => 0.9,
            ]],
            'labels' => array_keys($counts),
        ];
    }

    protected function getOptions(): RawJs
    {
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
