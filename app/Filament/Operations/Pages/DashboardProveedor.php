<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierCasesByServiceTypeChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierDashboardStatsOverview;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierIdentityHeader;
use App\Support\Operations\SupplierDashboardMetrics;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard del Proveedor.
 *
 * Solo lo ve un analista de un proveedor con la gestión Integracorp habilitada
 * y con el permiso "dashboard-proveedor" asignado por un SUPERADMIN.
 */
class DashboardProveedor extends Dashboard
{
    use AuthorizesDepartmentNavigation {
        canAccess as protected canAccessByDepartmentPermission;
    }

    protected static ?string $navigationLabel = 'DASHBOARD PROVEEDOR';

    protected static ?string $title = 'Dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = -1;

    protected static string $routePath = 'dashboard-proveedor';

    public static function canAccess(): bool
    {
        if (! SupplierDashboardMetrics::isAvailableFor(Auth::user())) {
            return false;
        }

        return static::canAccessByDepartmentPermission();
    }

    public function getSubheading(): ?string
    {
        $supplierName = SupplierDashboardMetrics::supplierName();

        return $supplierName === null
            ? null
            // : 'Indicadores operativos de '.$supplierName.'.'
            : 'INDICADORES OPERATIVOS';
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            SupplierIdentityHeader::class,
            SupplierDashboardStatsOverview::class,
            SupplierCasesByServiceTypeChart::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 1;
    }
}
