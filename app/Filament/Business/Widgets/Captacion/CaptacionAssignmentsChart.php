<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardChartJs as Js;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * ¿Responde cada colaborador a lo que se le asigna? Tareas y tickets de trabajo (Helpdesk)
 * asignados en el período frente a los ya resueltos, por responsable. Clic en una barra
 * para ver lo asignado a esa persona.
 */
class CaptacionAssignmentsChart extends ChartWidget
{
    use InteractsWithOperationsDashboard;

    public const ROW_HEIGHT = 46;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.operations.dashboard.chart';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string|Htmlable|null
    {
        return 'Asignados frente a resueltos por colaborador';
    }

    public function getDescription(): string|Htmlable|null
    {
        $data = $this->assignments();
        $assigned = $data['totals']['assigned'];
        $period = $this->period()->label();

        if ($assigned === 0) {
            return 'No se asignaron tareas ni tickets de trabajo en '.$period.'.';
        }

        $resolved = $data['totals']['resolved'];
        $rate = number_format($resolved * 100 / $assigned, 1, ',', '.');

        return $assigned.' asignados · '.$resolved.' resueltos ('.$rate.' %) · '.$period.'. Tareas y tickets del Helpdesk por responsable. Clic para ver lo asignado.';
    }

    public function getChartHeight(): string
    {
        $rows = count($this->assignments()['rows']);

        return min(520, max(200, $rows * self::ROW_HEIGHT + 70)).'px';
    }

    /**
     * @return array{rows: list<array{key: string, label: string, assigned: int, resolved: int, pending: int, rate: float}>, totals: array{assigned: int, resolved: int}}
     */
    public function assignments(): array
    {
        return ProspectDashboardMetrics::assignmentsByColaborador($this->period(), 12);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = $this->assignments()['rows'];
        $bar = [
            'borderRadius' => 4,
            'borderSkipped' => 'start',
            'maxBarThickness' => 16,
            'categoryPercentage' => 0.78,
            'barPercentage' => 0.9,
        ];

        return [
            'labels' => array_column($rows, 'label'),
            'datasets' => [
                [
                    'label' => 'Asignados',
                    'data' => array_column($rows, 'assigned'),
                    'role' => 'series1',
                    'keys' => array_column($rows, 'key'),
                    'pending' => array_column($rows, 'pending'),
                    'rates' => array_column($rows, 'rate'),
                    ...$bar,
                ],
                [
                    'label' => 'Resueltos',
                    'data' => array_column($rows, 'resolved'),
                    'role' => 'good',
                    ...$bar,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        $tooltip = Js::tooltip(<<<'JS'
            (tooltip, chart) => {
                const index = tooltip.dataPoints[0].dataIndex;
                const assigned = chart.data.datasets[0];
                const resolved = chart.data.datasets[1].data[index] ?? 0;
                const total = assigned.data[index] ?? 0;
                const pending = assigned.pending ? (assigned.pending[index] ?? 0) : total - resolved;
                return {
                    title: chart.data.labels[index],
                    rows: [
                        { swatch: ops.series1, value: fmt(total), unit: total === 1 ? 'asignado' : 'asignados' },
                        { swatch: ops.good, value: fmt(resolved), unit: resolved === 1 ? 'resuelto' : 'resueltos', big: false },
                    ],
                    lines: [
                        'Respuesta: ' + pct(resolved, total) + (pending > 0 ? ' · ' + fmt(pending) + (pending === 1 ? ' pendiente' : ' pendientes') : ' · al día'),
                    ],
                    footer: 'Clic para ver lo asignado',
                };
            }
        JS);

        $x = Js::axis('beginAtZero: true,', ticks: 'precision: 0, callback: (v) => fmt(v),');
        $y = Js::axis(grid: false, ticks: "autoSkip: false, font: { size: 11, weight: '500' }, callback: function (value) { const label = this.getLabelForValue(value); return label.length > 24 ? label.slice(0, 23) + '…' : label; },");

        return Js::options(<<<JS
            indexAxis: 'y',
            interaction: { mode: 'index', intersect: false, axis: 'y' },
            onClick: (event, elements, chart) => {
                if (!elements || !elements.length) { return; }
                const keys = chart.data.datasets[0].keys || [];
                const key = keys[elements[0].index];
                if (key === undefined) { return; }
                \$wire.dispatch('operations-dashboard-detail', { dimension: 'assignee', value: String(key) });
            },
            onHover: (event, elements) => {
                if (event?.native?.target) { event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; }
            },
            datasets: {
                bar: {
                    backgroundColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'],
                    hoverBackgroundColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'] + 'cc',
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
