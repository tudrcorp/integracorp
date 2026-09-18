<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsFactBarChart;
use App\Support\Operations\SupplierDashboardMetrics;

class SupplierCasesByServiceTypeChart extends OperationsFactBarChart
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected string $view = 'filament.operations.widgets.supplier-cases-by-service-type-chart';

    protected ?string $maxHeight = '26rem';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Total de casos por tipo de servicio';

    protected ?string $description = 'Casos distintos del proveedor por servicio prestado. Un caso que consumió varios servicios suma en cada barra.';

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        return SupplierDashboardMetrics::casesByServiceType();
    }

    protected function tooltipNoun(): string
    {
        return 'Casos';
    }
}
