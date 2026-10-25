<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Pages\Concerns\HasOperationsDashboardFilters;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierCaseStatusChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierConsultationsTrendChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierDashboardDetail;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierDoctorsRanking;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierIdentityHeader;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierKpiTiles;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierPatientsProfile;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierPriorityRanking;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierServiceTypeChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierWorkloadHeatmap;
use App\Support\Operations\SupplierDashboardMetrics;
use BackedEnum;
use Filament\Actions\Action;
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
    use HasOperationsDashboardFilters;

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
            : 'INDICADORES OPERATIVOS · '.$this->periodSubheading();
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
            /** Encabezado del proveedor: se mantiene igual, arriba de todo. */
            SupplierIdentityHeader::class,
            SupplierKpiTiles::class,
            SupplierConsultationsTrendChart::class,
            SupplierCaseStatusChart::class,
            SupplierServiceTypeChart::class,
            SupplierDoctorsRanking::class,
            SupplierPriorityRanking::class,
            SupplierWorkloadHeatmap::class,
            SupplierPatientsProfile::class,
            SupplierDashboardDetail::class,
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
}
