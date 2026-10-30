<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Business\Widgets\AffiliationIndividualChart;
use App\Filament\Business\Widgets\Dashboard\BusinessKpiTiles;
use App\Filament\Business\Widgets\Dashboard\BusinessNewVsRenewalCountChart;
use App\Filament\Business\Widgets\Dashboard\BusinessNewVsRenewalIncomeChart;
use App\Filament\Business\Widgets\Dashboard\BusinessNewVsRenewalTiles;
use App\Filament\Business\Widgets\Dashboard\BusinessTeamRanking;
use App\Filament\Business\Widgets\Dashboard\BusinessVenezuelaMap;
use App\Filament\Business\Widgets\Dashboard\BusinessWhiteCompanyRanking;
use App\Filament\Business\Widgets\IndividualQuoteChart;
use App\Filament\Business\Widgets\SalesForAgency;
use App\Filament\Business\Widgets\StatsOverview;
use App\Filament\Business\Widgets\StatsOverviewSaleBusiness;
use App\Filament\Business\Widgets\StatsOverviewSaleUsdVesBusiness;
use App\Filament\Business\Widgets\TwoStatsOverview;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\CorporateFunnel;
use App\Filament\Metrics\Widgets\Corporate\IncomeByAgencyRanking;
use App\Filament\Metrics\Widgets\Corporate\IncomeTrendChart;
use App\Filament\Metrics\Widgets\Corporate\NewVsDropoutsChart;
use App\Filament\Metrics\Widgets\Corporate\PlansSoldChart;
use App\Filament\Metrics\Widgets\Corporate\QuotesTrendChart;
use App\Filament\Metrics\Widgets\Corporate\SalesByAgentRanking;
use App\Filament\Operations\Pages\Concerns\HasCompactDashboardHeader;
use App\Filament\Widgets\WelcomeUserLiquidGlassWidget;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Estadísticas del negocio para analistas y administradores de Negocios: un solo
 * período para toda la página, comparado con el anterior.
 *
 * Estas cifras son de toda la empresa. El account manager solo puede ver su
 * cartera, así que conserva el tablero de siempre, que ya filtra por dueño.
 */
class Dashboard extends BaseDashboard
{
    use HasCompactDashboardHeader {
        getHeader as compactHeader;
        content as compactContent;
        filtersForm as compactFiltersForm;
    }

    protected static ?string $title = 'Dashboard';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        if (self::isAccountManager()) {
            return [
                WelcomeUserLiquidGlassWidget::class,
                StatsOverview::class,
                TwoStatsOverview::class,
                StatsOverviewSaleBusiness::class,
                StatsOverviewSaleUsdVesBusiness::class,
                AffiliationIndividualChart::class,
                IndividualQuoteChart::class,
                SalesForAgency::class,
            ];
        }

        return [
            WelcomeUserLiquidGlassWidget::class,
            BusinessKpiTiles::class,
            BusinessNewVsRenewalTiles::class,
            BusinessNewVsRenewalIncomeChart::class,
            BusinessNewVsRenewalCountChart::class,
            IncomeTrendChart::class,
            BusinessTeamRanking::class,
            QuotesTrendChart::class,
            CorporateFunnel::class,
            BusinessVenezuelaMap::class,
            SalesByAgentRanking::class,
            IncomeByAgencyRanking::class,
            PlansSoldChart::class,
            NewVsDropoutsChart::class,
            BusinessWhiteCompanyRanking::class,
            CorporateDetailWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return self::isAccountManager() ? parent::getColumns() : ['default' => 1, 'xl' => 12];
    }

    public function getHeader(): ?View
    {
        return self::isAccountManager() ? null : $this->compactHeader();
    }

    public function content(Schema $schema): Schema
    {
        return self::isAccountManager() ? parent::content($schema) : $this->compactContent($schema);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return self::isAccountManager() ? $schema : $this->compactFiltersForm($schema);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return self::isAccountManager() ? null : $this->periodSubheading();
    }

    protected function headerEyebrow(): string
    {
        return 'Negocios';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return self::isAccountManager() ? [] : [$this->presentationAction()];
    }

    private static function isAccountManager(): bool
    {
        $user = Auth::user();

        return $user instanceof User && (int) $user->is_accountManagers === 1;
    }
}
