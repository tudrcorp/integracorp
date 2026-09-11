<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\OperationsDashboardMetrics;

class ServicesByServiceTypeChart extends OperationsFactBarChart
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 6;

    protected ?string $heading = 'Total de servicios por tipo de servicio';

    protected ?string $description = 'Volumen de servicios agrupados por canal: laboratorios, medicamentos, imagenología, especialista y traslado.';

    protected function counts(): array
    {
        return OperationsDashboardMetrics::countsByServiceType();
    }

    protected function tooltipNoun(): string
    {
        return 'Servicios';
    }
}
