<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;

class CostByProviderRanking extends CorporateRanking
{
    public function getHeading(): string
    {
        return 'Costo médico por proveedor';
    }

    public function getDescription(): string
    {
        return 'Órdenes finalizadas en el período. Clic para ver las órdenes.';
    }

    protected function dimension(): string
    {
        return 'cost_provider';
    }

    protected function icon(): string
    {
        return 'heroicon-o-building-storefront';
    }

    protected function emptyText(): string
    {
        return 'No hay órdenes finalizadas en este período.';
    }

    protected function ranking(): array
    {
        return CorporateFinanceMetrics::costByProvider($this->period(), 8);
    }
}
