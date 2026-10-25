<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use Filament\Widgets\Widget;

/**
 * Renovaciones: lo que se renovó en el período y lo que vence pronto (ingreso
 * a cuidar). Reemplaza a los KPI de renovaciones del Escritorio anterior.
 */
class AdministrationRenewalsCard extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.administration.dashboard.renewals';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $current = CorporatePortfolioMetrics::kpis($period)['current'];

        return [
            'renewed' => (int) $current['renewed'],
            'expired' => (int) $current['expired'],
            'rate' => $current['renewal_rate'],
            'upcoming' => AdministrationFinanceMetrics::upcomingRenewals(),
            'horizon' => AdministrationFinanceMetrics::HORIZON_DAYS,
            'periodLabel' => $period->label(),
            'periodKey' => $period->cacheKey(),
        ];
    }
}
