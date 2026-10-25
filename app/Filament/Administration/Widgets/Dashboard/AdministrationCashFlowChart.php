<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateComparisonChart;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

class AdministrationCashFlowChart extends CorporateComparisonChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Ingresos frente a egresos';
    }

    protected function intro(): string
    {
        return 'Egresos: comisiones, costo médico y facturas de proveedores (sin nómina).';
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function series(): array
    {
        $data = AdministrationFinanceMetrics::cashFlow($this->period());

        return ['labels' => $data['labels'], 'first' => $data['income'], 'second' => $data['outflows']];
    }

    protected function names(): array
    {
        return ['Ingresos', 'Egresos'];
    }

    protected function differenceLabel(): string
    {
        return 'Resultado';
    }
}
