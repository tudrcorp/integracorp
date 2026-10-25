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
 * Pacientes distintos atendidos por franja de edad.
 *
 * Un solo color: el eje ya da el orden de las franjas y la altura da el valor
 * (una rampa de siete azules no se distingue y solo repetiría lo mismo).
 */
class OperationsAgeChart extends ChartWidget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 8;

    protected string $view = 'filament.operations.dashboard.chart';

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Pacientes por edad';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Pacientes distintos atendidos en el período, por franja de edad.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $bands = OperationsDashboardAnalytics::ageBands($this->period());

        return [
            'datasets' => [[
                'label' => 'Pacientes',
                'data' => array_values($bands),
                'borderRadius' => 4,
                'borderSkipped' => 'start',
                'maxBarThickness' => 34,
                'categoryPercentage' => 0.8,
                'barPercentage' => 0.92,
            ]],
            'labels' => array_keys($bands),
        ];
    }

    protected function getOptions(): RawJs
    {
        $tooltip = Js::tooltip(<<<'JS'
            (tooltip, chart) => {
                const point = tooltip.dataPoints[0];
                const total = point.dataset.data.reduce((sum, value) => sum + value, 0);
                return {
                    title: 'Pacientes de ' + point.label + ' años',
                    rows: [{ swatch: ops.series1, value: fmt(point.parsed.y), unit: point.parsed.y === 1 ? 'paciente' : 'pacientes' }],
                    lines: [pct(point.parsed.y, total) + ' de los pacientes atendidos'],
                    footer: 'Clic para ver sus servicios',
                };
            }
        JS);

        $click = Js::onClickDetail('age_band');
        $x = Js::axis(grid: false);
        $y = Js::axis('beginAtZero: true,', ticks: 'precision: 0, callback: (v) => fmt(v),');

        return Js::options(<<<JS
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
