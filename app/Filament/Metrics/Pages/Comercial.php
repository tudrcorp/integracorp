<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Pages\Concerns\IsCorporateMetricsPage;
use App\Filament\Metrics\Widgets\Corporate\CommercialKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\CorporateFunnel;
use App\Filament\Metrics\Widgets\Corporate\IncomeByAgencyRanking;
use App\Filament\Metrics\Widgets\Corporate\PlansSoldChart;
use App\Filament\Metrics\Widgets\Corporate\QuotesTrendChart;
use App\Filament\Metrics\Widgets\Corporate\SalesByAgentRanking;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Comercial: cotizaciones, conversión, red de ventas y lo que más se vende.
 */
class Comercial extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use IsCorporateMetricsPage;

    protected static ?string $navigationLabel = 'Comercial';

    protected static ?string $title = 'Comercial';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 3;

    protected static string $routePath = 'comercial';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            CommercialKpiTiles::class,
            QuotesTrendChart::class,
            CorporateFunnel::class,
            SalesByAgentRanking::class,
            IncomeByAgencyRanking::class,
            PlansSoldChart::class,
            CorporateDetailWidget::class,
        ];
    }
}
