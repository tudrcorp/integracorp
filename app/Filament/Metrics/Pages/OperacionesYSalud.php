<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Pages\Concerns\IsCorporateMetricsPage;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\CostByProviderRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsAgeChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsBusinessLineSplit;
use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles;
use App\Filament\Operations\Widgets\Dashboard\OperationsServicesStatusChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsServicesTrendChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsServiceTypeChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsStateRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsTopServicesRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsWorkloadHeatmap;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Operaciones y salud: el mismo tablero del panel de Operaciones (servicios,
 * estatus, tipos, carga de trabajo) más el costo médico por proveedor.
 */
class OperacionesYSalud extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use IsCorporateMetricsPage;

    protected static ?string $navigationLabel = 'Operaciones y salud';

    protected static ?string $title = 'Operaciones y salud';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 5;

    protected static string $routePath = 'operaciones-y-salud';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            OperationsKpiTiles::class,
            OperationsServicesTrendChart::class,
            OperationsServicesStatusChart::class,
            OperationsServiceTypeChart::class,
            OperationsBusinessLineSplit::class,
            OperationsStateRanking::class,
            OperationsWorkloadHeatmap::class,
            OperationsAgeChart::class,
            OperationsTopServicesRanking::class,
            CostByProviderRanking::class,
            OperationsDashboardDetail::class,
            CorporateDetailWidget::class,
        ];
    }
}
