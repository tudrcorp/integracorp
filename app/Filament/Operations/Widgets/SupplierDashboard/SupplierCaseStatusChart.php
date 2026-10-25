<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsServicesStatusChart;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Casos abiertos en el período, por su estado actual.
 */
class SupplierCaseStatusChart extends OperationsServicesStatusChart
{
    protected static ?int $sort = 4;

    public function getHeading(): string|Htmlable|null
    {
        return 'Estado de los casos';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Casos abiertos en el período, según su estado actual. Haga clic para ver el detalle.';
    }

    protected function statusCounts(): array
    {
        return SupplierDashboardAnalytics::caseStatus($this->period());
    }

    public function noun(): string
    {
        return 'casos';
    }

    public function detailDimension(): string
    {
        return 'case_status';
    }
}
