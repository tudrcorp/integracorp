<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Metrics\Widgets\Corporate\CorporateTrendChart;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use Filament\Support\RawJs;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Altas en el tiempo como barras (pedido de Negocios): con pocas altas por día, la curva
 * suavizada dibuja montañas que no existen. Mismo tooltip y misma comparación que la
 * tendencia de los dashboards nuevos; el período anterior va como barra gris al lado.
 */
class CaptacionRegistrationsTrend extends CorporateTrendChart
{
    private const BAR_DATASET_OPTIONS = <<<'JS'
        datasets: {
                bar: {
                    backgroundColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'],
                    hoverBackgroundColor: (ctx) => ops[ctx.dataset?.role ?? 'series1'] + 'cc',
                    borderColor: ops.surface,
                    borderWidth: 0,
                },
            },

    JS;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    public function getHeading(): string|Htmlable|null
    {
        return 'Evolución de las altas';
    }

    protected function subject(): string
    {
        return 'Prospectos registrados';
    }

    protected function noun(): string
    {
        return 'prospectos';
    }

    protected function trendData(): array
    {
        return ProspectDashboardMetrics::registrationsTrend($this->period());
    }

    /**
     * Las series de la tendencia, sin lo propio de una línea y con forma de barra.
     *
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $data = parent::getData();

        $data['datasets'] = array_map(static function (array $dataset): array {
            unset($dataset['fill'], $dataset['tension'], $dataset['pointRadius'], $dataset['pointHoverRadius'], $dataset['pointHitRadius']);

            return [
                ...$dataset,
                'borderWidth' => 0,
                'borderRadius' => 4,
                'borderSkipped' => 'start',
                'maxBarThickness' => 28,
                'categoryPercentage' => 0.72,
                'barPercentage' => 0.9,
            ];
        }, $data['datasets']);

        return $data;
    }

    /**
     * Mismas opciones que la tendencia (tooltip, ejes, leyenda), con el estilo de barra en
     * lugar del de línea. Si el bloque de la línea cambiara de forma, queda la línea.
     */
    protected function getOptions(): RawJs
    {
        $options = parent::getOptions()->toHtml();
        $bar = preg_replace('/datasets:\s*\{.*?(?=plugins:)/s', self::BAR_DATASET_OPTIONS, $options, 1, $count);

        return RawJs::make($count === 1 && is_string($bar) ? $bar : $options);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
