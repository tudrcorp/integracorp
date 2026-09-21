<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\OperationsDashboardMetrics;

class ServicesByStatusChart extends OperationsFactBarChart
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Total de servicios por estatus';

    protected ?string $description = 'Volumen de servicios según el estatus de la coordinación (pendiente, en gestión, finalizado).';

    protected function counts(): array
    {
        return OperationsDashboardMetrics::countsByServiceStatus();
    }

    protected function tooltipNoun(): string
    {
        return 'Servicios';
    }
}
