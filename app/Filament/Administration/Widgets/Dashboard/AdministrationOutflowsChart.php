<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateDonutChart;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

class AdministrationOutflowsChart extends CorporateDonutChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'En qué se va el dinero';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Egresos del período por concepto.';
    }

    protected function values(): array
    {
        return AdministrationFinanceMetrics::outflowComposition($this->period());
    }

    public function noun(): string
    {
        return 'egresos';
    }

    public function isMoney(): bool
    {
        return true;
    }

    protected function colorRoles(): array
    {
        return ['Comisiones' => 'series1', 'Costo médico' => 'series2', 'Facturas de proveedores' => 'violet'];
    }
}
