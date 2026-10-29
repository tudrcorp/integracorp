<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Pages\Concerns\HasCompactDashboardHeader;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkAgreements;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkClasses;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkDetail;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkKpiTiles;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkLetters;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkMap;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkProductivity;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkQuality;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkStatus;
use App\Support\Filament\OperationsPanelNavigationGroups;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Dashboard de proveedores: la red de jurídicos, naturales y aliados corporativos (estatus,
 * convenio general o preferencial, tipos, cartas de aceptación y mapa de Venezuela) y la
 * productividad del equipo de proveedores. Pensado para presentaciones corporativas.
 *
 * La red es foto a hoy y queda en caché hasta que cambia un proveedor; el período del encabezado
 * mueve las altas y la productividad.
 */
class DashboardRedProveedores extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use HasCompactDashboardHeader;

    protected static ?string $navigationLabel = 'Dashboard de proveedores';

    protected static ?string $title = 'Dashboard de proveedores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = OperationsPanelNavigationGroups::PROVIDERS;

    protected static ?int $navigationSort = -10;

    protected static string $routePath = 'dashboard-red-proveedores';

    public function getSubheading(): string|Htmlable|null
    {
        return 'La red es foto a hoy; el período mueve las altas y la productividad del equipo. Haga clic en el mapa o en cualquier barra para ver los proveedores.';
    }

    protected function headerEyebrow(): string
    {
        return 'Proveedores';
    }

    protected function headerEyebrowIcon(): string
    {
        return 'heroicon-m-building-office-2';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->presentationAction()];
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ProviderNetworkKpiTiles::class,
            ProviderNetworkMap::class,
            ProviderNetworkStatus::class,
            ProviderNetworkAgreements::class,
            ProviderNetworkClasses::class,
            ProviderNetworkLetters::class,
            ProviderNetworkProductivity::class,
            ProviderNetworkQuality::class,
            ProviderNetworkDetail::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 12];
    }
}
