<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Pages\Concerns\IsCorporateMetricsPage;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\LivesByAgeChart;
use App\Filament\Metrics\Widgets\Corporate\LivesByPlanChart;
use App\Filament\Metrics\Widgets\Corporate\NewVsDropoutsChart;
use App\Filament\Metrics\Widgets\Corporate\PortfolioKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\PortfolioStatusChart;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Cartera: cuántas afiliaciones entran, salen y se renuevan, y cómo es la
 * población cubierta hoy.
 */
class Cartera extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use IsCorporateMetricsPage;

    protected static ?string $navigationLabel = 'Cartera';

    protected static ?string $title = 'Cartera';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 4;

    protected static string $routePath = 'cartera';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            PortfolioKpiTiles::class,
            NewVsDropoutsChart::class,
            PortfolioStatusChart::class,
            LivesByPlanChart::class,
            LivesByAgeChart::class,
            CorporateDetailWidget::class,
        ];
    }
}
