<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Operations\Widgets\Dashboard\FinishedServicesMonthlyChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardStatsOverview;
use App\Filament\Operations\Widgets\Dashboard\TopPatientsMedicalDischargeChart;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class DashboardOperaciones extends Dashboard
{
    protected static ?string $navigationLabel = 'Dashboard Operaciones';

    protected static ?string $title = 'Dashboard Operaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = -1;

    protected static string $routePath = 'dashboard-operaciones';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            OperationsDashboardStatsOverview::class,
            TopPatientsMedicalDischargeChart::class,
            FinishedServicesMonthlyChart::class,
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
