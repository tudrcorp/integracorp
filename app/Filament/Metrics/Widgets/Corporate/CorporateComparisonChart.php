<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardChartJs as Js;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Dos cifras de la misma unidad frente a frente en el tiempo (un solo eje):
 * ingresos y costos, altas y bajas, tickets creados y resueltos. El tooltip
 * da la diferencia del punto.
 */
abstract class CorporateComparisonChart extends ChartWidget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.operations.dashboard.chart';

    protected ?string $maxHeight = '340px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    /**
     * @return array{labels: list<string>, first: list<float>, second: list<float>}
     */
    abstract protected function series(): array;

    /**
     * Nombre de cada serie: [primera, segunda].
     *
     * @return array{0: string, 1: string}
     */
    abstract protected function names(): array;

    /**
     * Texto de la diferencia en el tooltip («Margen del punto», «Altas netas»).
     */
    abstract protected function differenceLabel(): string;

    protected function isMoney(): bool
    {
        return false;
    }

    /**
     * Qué se cuenta (plural) cuando no es dinero.
     */
    protected function noun(): string
    {
        return '';
    }

    protected function intro(): string
    {
        return '';
    }

    public function getDescription(): string|Htmlable|null
    {
        $period = $this->period();
        $unit = match ($period->granularity()) {
            OperationsDashboardPeriod::GRANULARITY_WEEK => 'por semana',
            OperationsDashboardPeriod::GRANULARITY_MONTH => 'por mes',
            default => 'por día',
        };

        return trim($this->intro().' '.ucfirst($unit).' · '.$period->label().'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $series = $this->series();
        [$first, $second] = $this->names();
        $bar = [
            'borderRadius' => 4,
            'borderSkipped' => 'start',
            'maxBarThickness' => 26,
            'categoryPercentage' => 0.72,
            'barPercentage' => 0.9,
        ];

        return [
            'datasets' => [
                ['label' => $first, 'data' => $series['first'], 'role' => 'series1', ...$bar],
                ['label' => $second, 'data' => $series['second'], 'role' => 'series2', ...$bar],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        $money = $this->isMoney();
        $format = $money ? 'money' : 'fmt';
        $tick = $money ? 'moneyShort' : 'fmt';
        $unit = Js::jsString($money ? '' : $this->noun());
        $difference = Js::jsString($this->differenceLabel());

        $tooltip = Js::tooltip(<<<JS
            (tooltip, chart) => {
                const index = tooltip.dataPoints[0].dataIndex;
                const datasets = chart.data.datasets;
                const first = datasets[0].data[index] ?? 0;
                const second = datasets[1].data[index] ?? 0;
                const gap = first - second;
                return {
                    title: chart.data.labels[index],
                    rows: [
                        { swatch: ops.series1, value: {$format}(first), unit: datasets[0].label.toLowerCase() + ({$unit} ? ' · ' + {$unit} : '') },
                        { swatch: ops.series2, value: {$format}(second), unit: datasets[1].label.toLowerCase(), big: false },
                    ],
                    lines: [{$difference} + ': ' + (gap < 0 ? '−' : '') + {$format}(Math.abs(gap))],
                };
            }
        JS);

        $x = Js::axis(grid: false, ticks: 'maxRotation: 0, autoSkip: true, autoSkipPadding: 18,');
        $y = Js::axis('beginAtZero: true,', ticks: 'precision: 0, callback: (v) => '.$tick.'(v),');

        return Js::options(<<<JS
            interaction: { mode: 'index', intersect: false },
            datasets: {
                bar: {
                    backgroundColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'],
                    hoverBackgroundColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'] + 'cc',
                    borderColor: ops.surface,
                    borderWidth: { top: 0, right: 2, bottom: 0, left: 0 },
                },
            },
            plugins: {
                legend: { display: true, position: 'top', align: 'end', labels: { color: ops.text, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10, padding: 16 } },
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
