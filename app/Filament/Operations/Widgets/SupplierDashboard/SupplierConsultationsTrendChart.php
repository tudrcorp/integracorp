<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsServicesTrendChart;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Consultas del proveedor en el tiempo, con el período anterior superpuesto.
 */
class SupplierConsultationsTrendChart extends OperationsServicesTrendChart
{
    protected static ?int $sort = 3;

    public function getHeading(): string|Htmlable|null
    {
        return 'Evolución de las consultas';
    }

    protected function noun(): string
    {
        return 'consultas';
    }

    protected function trendData(): array
    {
        return SupplierDashboardAnalytics::trend($this->period());
    }
}
