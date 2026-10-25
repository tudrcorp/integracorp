<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsServiceTypeChart;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Casos distintos del proveedor por tipo de servicio prestado en el período.
 */
class SupplierServiceTypeChart extends OperationsServiceTypeChart
{
    protected static ?int $sort = 5;

    public function getHeading(): string|Htmlable|null
    {
        return 'Casos por tipo de servicio';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Un caso que consumió varios servicios suma en cada barra. Haga clic para ver los casos.';
    }

    protected function typeCounts(): array
    {
        return SupplierDashboardAnalytics::serviceTypes($this->period());
    }

    protected function noun(): string
    {
        return 'casos';
    }
}
