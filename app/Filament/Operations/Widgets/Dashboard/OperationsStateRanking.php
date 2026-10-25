<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;

/**
 * Dónde se prestan los servicios: estados con más servicios en el período.
 */
class OperationsStateRanking extends OperationsRankingWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string
    {
        return 'Servicios por estado';
    }

    public function getDescription(): string
    {
        return 'Dónde se concentra la operación. Haga clic para ver el detalle.';
    }

    protected function dimension(): string
    {
        return 'state';
    }

    protected function icon(): string
    {
        return 'heroicon-o-map';
    }

    protected function ranking(): array
    {
        return OperationsDashboardAnalytics::byState($this->period(), 8);
    }
}
