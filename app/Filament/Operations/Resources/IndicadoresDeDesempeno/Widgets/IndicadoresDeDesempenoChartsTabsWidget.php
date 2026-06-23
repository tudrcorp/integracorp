<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets;

use App\Support\IndicadoresDeDesempeno\IndicadoresDeDesempenoChartTabs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Widget;

class IndicadoresDeDesempenoChartsTabsWidget extends Widget
{
    protected string $view = 'filament.operations.indicadores-de-desempeno-charts-tabs';

    protected int|string|array $columnSpan = 'full';

    public string $activeChartTab = IndicadoresDeDesempenoChartTabs::TAB_HELPDESK_TICKETS;

    /**
     * @return array<string, array{label: string, widget: class-string<ChartWidget>}>
     */
    public function getChartTabs(): array
    {
        return IndicadoresDeDesempenoChartTabs::definitions();
    }

    /**
     * @return class-string<ChartWidget>
     */
    public function getActiveChartWidgetClass(): string
    {
        return IndicadoresDeDesempenoChartTabs::widgetClassForTab($this->activeChartTab);
    }
}
