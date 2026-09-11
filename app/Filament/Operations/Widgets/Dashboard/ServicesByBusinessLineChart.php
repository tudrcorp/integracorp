<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\OperationsDashboardMetrics;

class ServicesByBusinessLineChart extends OperationsFactBarChart
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Total de servicios por línea de negocio';

    protected ?string $description = 'Volumen de servicios agrupados por línea de negocio del paciente (corporativos o individuales).';

    protected function counts(): array
    {
        return OperationsDashboardMetrics::countsByBusinessLine();
    }

    protected function tooltipNoun(): string
    {
        return 'Servicios';
    }
}
