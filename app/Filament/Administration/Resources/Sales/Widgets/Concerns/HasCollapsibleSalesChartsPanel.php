<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Sales\Widgets\Concerns;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;

trait HasCollapsibleSalesChartsPanel
{
    public bool $sectionExpanded = false;

    protected ?string $heading = 'GRÁFICOS DE VENTAS';

    protected ?string $description = 'Resumen anual y distribución por plan del mes actual';

    public function toggleSection(): void
    {
        $this->sectionExpanded = ! $this->sectionExpanded;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function salesChartsPanelVariant(): string
    {
        return 'graficos';
    }

    public function salesChartsPanelIcon(): Heroicon
    {
        return Heroicon::OutlinedChartBar;
    }

    /**
     * @return class-string<ChartWidget>
     */
    abstract public function yearChartWidget(): string;

    /**
     * @return class-string<ChartWidget>
     */
    abstract public function planChartWidget(): string;
}
