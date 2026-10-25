<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use App\Support\Operations\Dashboard\OperationsDashboardChartJs as Js;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Servicios registrados en el tiempo, con el período anterior superpuesto.
 */
class OperationsServicesTrendChart extends ChartWidget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected string $view = 'filament.operations.dashboard.chart';

    protected ?string $maxHeight = '380px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    public function getHeading(): string|Htmlable|null
    {
        return 'Evolución de los servicios';
    }

    /**
     * Qué se cuenta (plural): «servicios» aquí, «consultas» en el del proveedor.
     */
    protected function noun(): string
    {
        return 'servicios';
    }

    /**
     * Si la serie es dinero (USD): cambia el formato del tooltip y del eje.
     */
    protected function isMoney(): bool
    {
        return false;
    }

    /**
     * @return array{labels: list<string>, current: list<int|float>, previous: list<int|float>, previous_labels: list<string>}
     */
    protected function trendData(): array
    {
        return OperationsDashboardAnalytics::trend($this->period());
    }

    public function getDescription(): string|Htmlable|null
    {
        $period = $this->period();
        $unit = match ($period->granularity()) {
            OperationsDashboardPeriod::GRANULARITY_WEEK => 'por semana',
            OperationsDashboardPeriod::GRANULARITY_MONTH => 'por mes',
            default => 'por día',
        };

        return ucfirst($this->noun()).' registrados '.$unit.' · '.$period->label()
            .($period->compare ? ' frente a '.$period->previousLabel() : '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $period = $this->period();
        $trend = $this->trendData();

        $datasets = [[
            'label' => 'Período actual',
            'data' => $trend['current'],
            'role' => 'series1',
            'fill' => true,
            'tension' => 0.35,
            'borderWidth' => 2,
            'pointRadius' => 0,
            'pointHoverRadius' => 5,
            'pointHitRadius' => 12,
            'order' => 1,
        ]];

        if ($period->compare) {
            $datasets[] = [
                'label' => 'Período anterior',
                'data' => $trend['previous'],
                'periodLabels' => $trend['previous_labels'],
                'role' => 'previous',
                'fill' => false,
                'tension' => 0.35,
                'borderWidth' => 2,
                'pointRadius' => 0,
                'pointHoverRadius' => 4,
                'pointHitRadius' => 12,
                'order' => 2,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $trend['labels']];
    }

    protected function getOptions(): RawJs
    {
        $money = $this->isMoney();
        $plural = Js::jsString($money ? '' : $this->noun());
        $singular = Js::jsString($money ? '' : Js::singular($this->noun()));
        $format = $money ? 'money' : 'fmt';
        $tick = $money ? 'moneyShort' : 'fmt';
        $missing = Js::jsString('Sin '.($money ? 'movimientos' : $this->noun()).' en el mismo punto del período anterior');

        $tooltip = Js::tooltip(<<<JS
            (tooltip, chart) => {
                const index = tooltip.dataPoints[0].dataIndex;
                const datasets = chart.data.datasets;
                const current = datasets[0] ? (datasets[0].data[index] ?? 0) : 0;
                const spec = {
                    title: chart.data.labels[index],
                    rows: [{ swatch: ops.series1, value: {$format}(current), unit: current === 1 ? {$singular} : {$plural} }],
                    lines: [],
                };

                if (datasets[1]) {
                    const previous = datasets[1].data[index] ?? 0;
                    const label = datasets[1].periodLabels ? datasets[1].periodLabels[index] : '';
                    spec.rows.push({ swatch: ops.previous, value: {$format}(previous), unit: 'período anterior' + (label ? ' (' + label + ')' : ''), big: false });

                    if (previous > 0) {
                        const change = (current - previous) * 100 / previous;
                        spec.lines.push((change >= 0 ? '+' : '−') + nf1(Math.abs(change)) + ' % frente al período anterior');
                    } else if (current > 0) {
                        spec.lines.push({$missing});
                    }
                }

                return spec;
            }
        JS);

        $x = Js::axis(grid: false, ticks: 'maxRotation: 0, autoSkip: true, autoSkipPadding: 18,');
        $y = Js::axis('beginAtZero: true,', ticks: 'precision: 0, callback: (v) => '.$tick.'(v),');

        return Js::options(<<<JS
            interaction: { mode: 'index', intersect: false },
            datasets: {
                line: {
                    borderColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'],
                    pointBackgroundColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'],
                    pointBorderColor: ops.surface,
                    pointBorderWidth: 2,
                    backgroundColor: (ctx) => {
                        const chart = ctx.chart;
                        const area = chart.chartArea;
                        if (!area) { return 'transparent'; }
                        const gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                        gradient.addColorStop(0, ops.series1 + '40');
                        gradient.addColorStop(1, ops.series1 + '00');
                        return gradient;
                    },
                },
            },
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
        return 'line';
    }
}
