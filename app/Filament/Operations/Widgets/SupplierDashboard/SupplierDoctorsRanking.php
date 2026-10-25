<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsRankingWidget;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;

/**
 * Médicos del proveedor con más consultas en el período.
 */
class SupplierDoctorsRanking extends OperationsRankingWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string
    {
        return 'Médicos con más consultas';
    }

    public function getDescription(): string
    {
        return 'Consultas registradas por cada médico en el período. Haga clic para ver sus casos.';
    }

    protected function dimension(): string
    {
        return 'doctor';
    }

    protected function noun(): string
    {
        return 'consultas';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user-circle';
    }

    protected function emptyText(): string
    {
        return 'No hay consultas en este período.';
    }

    protected function ranking(): array
    {
        return SupplierDashboardAnalytics::doctors($this->period(), 8);
    }
}
