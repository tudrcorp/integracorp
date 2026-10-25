<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;

/**
 * Servicios específicos más solicitados en el período.
 */
class OperationsTopServicesRanking extends OperationsRankingWidget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string
    {
        return 'Servicios más solicitados';
    }

    public function getDescription(): string
    {
        return 'Los 10 servicios específicos con más solicitudes.';
    }

    protected function dimension(): string
    {
        return 'specific_service';
    }

    protected function icon(): string
    {
        return 'heroicon-o-beaker';
    }

    protected function ranking(): array
    {
        return OperationsDashboardAnalytics::topServices($this->period(), 10);
    }
}
