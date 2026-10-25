<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsRankingWidget;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;

/**
 * Casos abiertos en el período, por prioridad.
 */
class SupplierPriorityRanking extends OperationsRankingWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string
    {
        return 'Prioridad de los casos';
    }

    public function getDescription(): string
    {
        return 'Con qué urgencia llegaron los casos del período. Haga clic para ver el detalle.';
    }

    protected function dimension(): string
    {
        return 'priority';
    }

    protected function noun(): string
    {
        return 'casos';
    }

    protected function icon(): string
    {
        return 'heroicon-o-bolt';
    }

    protected function emptyText(): string
    {
        return 'No hay casos abiertos en este período.';
    }

    protected function ranking(): array
    {
        return SupplierDashboardAnalytics::priorities($this->period());
    }
}
