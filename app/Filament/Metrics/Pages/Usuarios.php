<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Pages\Concerns\IsCorporateMetricsPage;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\LeastActiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\NetworkActiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\NetworkByTypeChart;
use App\Filament\Metrics\Widgets\Corporate\TopActiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\TopProductiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\UsersAccountsChart;
use App\Filament\Metrics\Widgets\Corporate\UsersActivityHeatmap;
use App\Filament\Metrics\Widgets\Corporate\UsersComparisonTable;
use App\Filament\Metrics\Widgets\Corporate\UsersDepartmentHoursChart;
use App\Filament\Metrics\Widgets\Corporate\UsersDepartmentRecordsChart;
use App\Filament\Metrics\Widgets\Corporate\UsersHoursTrendChart;
use App\Filament\Metrics\Widgets\Corporate\UsersInsightsWidget;
use App\Filament\Metrics\Widgets\Corporate\UsersKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\UsersPanelsChart;
use App\Filament\Metrics\Widgets\Corporate\UsersTimeCompositionChart;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Usuarios: cómo usa el sistema cada persona, cuánto produce y cómo se compara
 * con su área y con la empresa. Primero el personal (internos y médicos);
 * al final, la red comercial, que no entra en los promedios del personal.
 */
class Usuarios extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use IsCorporateMetricsPage;

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $title = 'Usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 7;

    protected static string $routePath = 'usuarios';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            UsersKpiTiles::class,
            UsersHoursTrendChart::class,
            UsersInsightsWidget::class,
            TopActiveUsersRanking::class,
            LeastActiveUsersRanking::class,
            TopProductiveUsersRanking::class,
            UsersDepartmentHoursChart::class,
            UsersDepartmentRecordsChart::class,
            UsersTimeCompositionChart::class,
            UsersActivityHeatmap::class,
            UsersPanelsChart::class,
            UsersComparisonTable::class,
            NetworkActiveUsersRanking::class,
            NetworkByTypeChart::class,
            UsersAccountsChart::class,
            CorporateDetailWidget::class,
        ];
    }
}
