<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard\Concerns;

use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * Lo común a los widgets del Dashboard de Operaciones: el período sale de los
 * filtros de la página (todos los widgets miran lo mismo) y el modo
 * presentación los refresca con un evento.
 */
trait InteractsWithOperationsDashboard
{
    use InteractsWithPageFilters;

    /**
     * Esqueleto mientras el widget carga (carga diferida): misma columna y altura
     * aproximada, así la página no salta al completarse.
     */
    public function placeholder(): View
    {
        return view('filament.operations.dashboard.partials.skeleton', [
            'height' => $this->getPlaceholderHeight() ?? '18rem',
            ...$this->getPlaceholderData(),
        ]);
    }

    protected function period(): OperationsDashboardPeriod
    {
        return OperationsDashboardPeriod::fromFilters($this->pageFilters);
    }

    #[On('operations-dashboard-refresh')]
    public function refreshOperationsDashboard(): void
    {
        if ($this instanceof ChartWidget) {
            $this->cachedData = null;
            $this->updateChartData();
        }
    }
}
