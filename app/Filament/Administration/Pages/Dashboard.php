<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Administration\Widgets\Dashboard\AdministrationCashFlowChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationCommissionsTrendChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationCostByProviderRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationDashboardDetail;
use App\Filament\Administration\Widgets\Dashboard\AdministrationKpiTiles;
use App\Filament\Administration\Widgets\Dashboard\AdministrationOutflowsChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationPayablesRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationPeopleByDepartmentRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationPeopleKpiTiles;
use App\Filament\Administration\Widgets\Dashboard\AdministrationReceivablesAgingChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationReceivablesByAgencyRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationRenewalsCard;
use App\Filament\Administration\Widgets\Dashboard\AdministrationSectionHeading;
use App\Filament\Administration\Widgets\Dashboard\AdministrationSectionNav;
use App\Filament\Administration\Widgets\Dashboard\AdministrationSeniorityChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationTdevRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationUpcomingCollectionsChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationUpcomingDates;
use App\Filament\Administration\Widgets\Dashboard\AdministrationWhiteCompanyRanking;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Widgets\Corporate\CommissionsChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeByAgencyRanking;
use App\Filament\Metrics\Widgets\Corporate\IncomeByPlanChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeByTypeChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeTrendChart;
use App\Filament\Operations\Pages\Concerns\HasCompactDashboardHeader;
use App\Filament\Widgets\WelcomeUserLiquidGlassWidget;
use App\Support\Filament\Administration\AdministrationPanelHomeFallback;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;

/**
 * Escritorio del panel de Administración: tablero financiero de la empresa
 * (flujo de caja, cobranza, ventas, comisiones, proveedores y RRHH) con el
 * mismo lenguaje de los dashboards de Operaciones y Métricas. La bienvenida
 * de arriba se conserva tal cual.
 *
 * Reemplaza a Filament\Pages\Dashboard para que su visibilidad se administre
 * como cualquier otro ítem de menú (permiso "escritorio" del módulo ADMINISTRACION),
 * conservando la ruta filament.administration.pages.dashboard y los widgets del panel.
 */
class Dashboard extends BaseDashboard
{
    use AuthorizesDepartmentNavigation;
    use HasCompactDashboardHeader;

    /**
     * Secciones del Escritorio: ancla, título, ícono y descripción. La barra de
     * accesos y los títulos de sección salen de aquí.
     *
     * @var list<array{anchor: string, label: string, icon: string, description: string}>
     */
    public const SECTIONS = [
        ['anchor' => 'admin-flujo', 'label' => 'Flujo de caja', 'icon' => 'heroicon-o-arrows-right-left', 'description' => 'Lo que entra frente a lo que sale.'],
        ['anchor' => 'admin-cobranza', 'label' => 'Cobranza', 'icon' => 'heroicon-o-clock', 'description' => 'Lo vencido, quién debe y lo que debería entrar pronto.'],
        ['anchor' => 'admin-ventas', 'label' => 'Ventas', 'icon' => 'heroicon-o-banknotes', 'description' => 'De dónde vienen los ingresos, TDEV y renovaciones.'],
        ['anchor' => 'admin-comisiones', 'label' => 'Comisiones', 'icon' => 'heroicon-o-user-group', 'description' => 'Lo generado y a quién.'],
        ['anchor' => 'admin-proveedores', 'label' => 'Proveedores', 'icon' => 'heroicon-o-building-storefront', 'description' => 'Facturas por pagar y costo médico.'],
        ['anchor' => 'admin-rrhh', 'label' => 'RRHH', 'icon' => 'heroicon-o-identification', 'description' => 'Plantilla, costo, antigüedad y próximas fechas.'],
    ];

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -2;

    /**
     * El Escritorio es la URL raíz del panel: si el usuario no lo tiene asignado
     * se le lleva a su primer módulo disponible en lugar de mostrarle un 403.
     */
    public function mountCanAuthorizeAccess(): void
    {
        if (static::canAccess()) {
            return;
        }

        $fallbackUrl = AdministrationPanelHomeFallback::url();

        if ($fallbackUrl === null) {
            abort(403);
        }

        $this->redirect($fallbackUrl);
    }

    protected function headerEyebrow(): string
    {
        return 'Administración';
    }

    protected function headerEyebrowIcon(): string
    {
        return 'heroicon-m-building-library';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->presentationAction()];
    }

    /**
     * @return array<class-string|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            WelcomeUserLiquidGlassWidget::class,
            AdministrationSectionNav::class,
            AdministrationKpiTiles::class,
            self::section('admin-flujo'),
            AdministrationCashFlowChart::class,
            AdministrationOutflowsChart::class,
            self::section('admin-cobranza'),
            AdministrationUpcomingCollectionsChart::class,
            AdministrationReceivablesAgingChart::class,
            AdministrationReceivablesByAgencyRanking::class,
            self::section('admin-ventas'),
            IncomeTrendChart::class,
            IncomeByTypeChart::class,
            IncomeByPlanChart::class,
            IncomeByAgencyRanking::class,
            AdministrationWhiteCompanyRanking::class,
            AdministrationTdevRanking::class,
            AdministrationRenewalsCard::class,
            self::section('admin-comisiones'),
            AdministrationCommissionsTrendChart::class,
            CommissionsChart::class,
            self::section('admin-proveedores'),
            AdministrationPayablesRanking::class,
            AdministrationCostByProviderRanking::class,
            self::section('admin-rrhh'),
            AdministrationPeopleKpiTiles::class,
            AdministrationPeopleByDepartmentRanking::class,
            AdministrationSeniorityChart::class,
            AdministrationUpcomingDates::class,
            AdministrationDashboardDetail::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 12,
        ];
    }

    private static function section(string $anchor): WidgetConfiguration
    {
        $section = collect(self::SECTIONS)->firstWhere('anchor', $anchor);

        return AdministrationSectionHeading::make([
            'anchor' => $anchor,
            'title' => $section['label'],
            'description' => $section['description'],
            'icon' => $section['icon'],
        ]);
    }
}
