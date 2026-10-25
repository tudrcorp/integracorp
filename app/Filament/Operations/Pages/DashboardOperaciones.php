<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Pages\Concerns\HasOperationsDashboardFilters;
use App\Filament\Operations\Widgets\Dashboard\OperationsAgeChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsBusinessLineSplit;
use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles;
use App\Filament\Operations\Widgets\Dashboard\OperationsServicesStatusChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsServicesTrendChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsServiceTypeChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsStateRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsTopPatientsRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsTopServicesRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsWorkloadHeatmap;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Dashboard de Operaciones: comportamiento del servicio en un período, comparado
 * con el período anterior. Pensado también para presentaciones (modo
 * presentación a pantalla completa con actualización automática).
 *
 * Todos los widgets leen el mismo período desde los filtros de la página.
 */
class DashboardOperaciones extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use HasOperationsDashboardFilters;

    protected static ?string $navigationLabel = 'Dashboard Operaciones';

    protected static ?string $title = 'Dashboard Operaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = -1;

    protected static string $routePath = 'dashboard-operaciones';

    public function getSubheading(): string|Htmlable|null
    {
        return $this->periodSubheading();
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
            OperationsKpiTiles::class,
            OperationsServicesTrendChart::class,
            OperationsServicesStatusChart::class,
            OperationsServiceTypeChart::class,
            OperationsBusinessLineSplit::class,
            OperationsStateRanking::class,
            OperationsWorkloadHeatmap::class,
            OperationsAgeChart::class,
            OperationsTopServicesRanking::class,
            OperationsTopPatientsRanking::class,
            OperationsDashboardDetail::class,
        ];
    }

    /**
     * Grilla de 12 columnas en pantallas grandes.
     *
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
