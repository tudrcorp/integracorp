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
 * Estatus de los servicios del período: anillo con el total al centro y leyenda
 * con conteo y porcentaje (el estatus nunca se lee solo por color).
 */
class OperationsServicesStatusChart extends ChartWidget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected string $view = 'filament.operations.dashboard.status-chart';

    protected ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Estatus de los servicios';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Dónde está cada servicio del período. Haga clic para ver el detalle.';
    }

    /**
     * Conteo por estatus del período. El del proveedor cuenta casos.
     *
     * @return array<string, int>
     */
    protected function statusCounts(): array
    {
        return OperationsDashboardAnalytics::byStatus($this->period());
    }

    public function noun(): string
    {
        return 'servicios';
    }

    /**
     * Color por etiqueta (rol de la paleta). Vacío: colores de estado
     * ({@see Js::statusRole()}). Para categorías sin significado de estado se
     * usan los roles categóricos (`series1`, `series2`, `violet`…).
     *
     * @return array<string, string>
     */
    protected function colorRoles(): array
    {
        return [];
    }

    protected function roleFor(string $label): string
    {
        return $this->colorRoles()[$label] ?? Js::statusRole($label);
    }

    public function detailDimension(): string
    {
        return 'service_status';
    }

    /**
     * Si los valores son dinero (USD): cambia el formato de la leyenda, el
     * centro del anillo y el tooltip.
     */
    public function isMoney(): bool
    {
        return false;
    }

    public function formatValue(int|float $value): string
    {
        return ($this->isMoney() ? 'US$ ' : '').number_format((float) $value, 0, ',', '.');
    }

    /**
     * @return list<array{label: string, value: int|float, percent: string, role: string}>
     */
    public function getLegendRows(): array
    {
        $counts = $this->statusCounts();
        $total = array_sum($counts);
        $rows = [];

        foreach ($counts as $label => $value) {
            $rows[] = [
                'label' => (string) $label,
                'value' => $value,
                'percent' => $total > 0 ? number_format($value * 100 / $total, 1, ',', '.').' %' : '0 %',
                'role' => $this->roleFor((string) $label),
            ];
        }

        return $rows;
    }

    public function getTotal(): int|float
    {
        return array_sum($this->statusCounts());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = $this->statusCounts();

        return [
            'datasets' => [[
                'label' => 'Servicios',
                'data' => array_values($counts),
                'roles' => array_map(fn (string $label): string => $this->roleFor($label), array_map('strval', array_keys($counts))),
                'borderWidth' => 2,
                'hoverOffset' => 6,
                'spacing' => 0,
            ]],
            'labels' => array_keys($counts),
        ];
    }

    protected function getOptions(): RawJs
    {
        $plural = Js::jsString($this->noun());
        $singular = Js::jsString(Js::singular($this->noun()));
        $share = Js::jsString(' de los '.$this->noun().' del período');
        $footer = $this->detailDimension() !== '' ? Js::jsString('Clic para ver estos '.$this->noun()) : 'null';
        $money = $this->isMoney();
        $format = $money ? 'money' : 'fmt';
        $unit = $money ? "''" : 'point.parsed === 1 ? '.$singular.' : '.$plural;

        $tooltip = Js::tooltip(<<<JS
            (tooltip, chart) => {
                const point = tooltip.dataPoints[0];
                const total = point.dataset.data.reduce((sum, value) => sum + value, 0);
                return {
                    title: 'Estatus · ' + point.label,
                    rows: [{ swatch: point.dataset.roles ? (ops[point.dataset.roles[point.dataIndex]] ?? ops.muted) : statusColor(point.label), value: {$format}(point.parsed), unit: {$unit} }],
                    lines: [pct(point.parsed, total) + {$share}],
                    footer: {$footer},
                };
            }
        JS);

        $click = $this->detailDimension() !== '' ? Js::onClickDetail($this->detailDimension()) : '';

        return Js::options(<<<JS
            cutout: '72%',
            {$click}
            datasets: {
                doughnut: {
                    backgroundColor: (ctx) => ctx.dataset.roles ? (ops[ctx.dataset.roles[ctx.dataIndex]] ?? ops.muted) : statusColor(ctx.chart.data.labels[ctx.dataIndex]),
                    borderColor: ops.surface,
                    hoverBorderColor: ops.surface,
                },
            },
            plugins: {
                legend: { display: false },
                {$tooltip}
            },
            scales: { x: { display: false }, y: { display: false } },
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
        return 'doughnut';
    }
}
