<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Pages\Concerns\IsCorporateMetricsPage;
use App\Filament\Metrics\Widgets\Corporate\CommissionsChart;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\CostByProviderRanking;
use App\Filament\Metrics\Widgets\Corporate\FinanceKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\IncomeByAgencyRanking;
use App\Filament\Metrics\Widgets\Corporate\IncomeByPlanChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeByTypeChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeVsCostsChart;
use App\Filament\Metrics\Widgets\Corporate\ReceivablesAgingChart;
use App\Filament\Metrics\Widgets\Corporate\ReceivablesByAgencyRanking;
use App\Filament\Metrics\Widgets\Corporate\WhiteCompanyRanking;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Finanzas: ingresos, comisiones, costo médico, margen estimado y cuentas por
 * cobrar. Todo en USD.
 */
class Finanzas extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use IsCorporateMetricsPage;

    protected static ?string $navigationLabel = 'Finanzas';

    protected static ?string $title = 'Finanzas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    protected static string $routePath = 'finanzas';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            FinanceKpiTiles::class,
            IncomeVsCostsChart::class,
            IncomeByTypeChart::class,
            IncomeByPlanChart::class,
            IncomeByAgencyRanking::class,
            CommissionsChart::class,
            ReceivablesAgingChart::class,
            ReceivablesByAgencyRanking::class,
            CostByProviderRanking::class,
            WhiteCompanyRanking::class,
            CorporateDetailWidget::class,
        ];
    }
}
