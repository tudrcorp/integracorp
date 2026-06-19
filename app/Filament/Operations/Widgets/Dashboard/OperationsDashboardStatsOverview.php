<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\OperationsDashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsDashboardStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $cardClass = 'overflow-hidden rounded-2xl border border-slate-200/70 bg-gradient-to-br from-white via-slate-50/90 to-white shadow-[0_12px_40px_-16px_rgba(15,23,42,0.14)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[0_18px_48px_-18px_rgba(15,23,42,0.22)] dark:border-white/10 dark:from-slate-900/90 dark:via-slate-950/95 dark:to-slate-900/90';

        return [
            Stat::make('Pacientes Asociados', number_format(OperationsDashboardMetrics::associatedPatientsCount()))
                ->description('Vinculados a afiliación individual o corporativa')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info')
                ->icon('heroicon-o-users')
                ->extraAttributes(['class' => $cardClass]),
            Stat::make('Casos de Alta Médica', number_format(OperationsDashboardMetrics::medicalDischargeCasesCount()))
                ->description('Casos cerrados con alta médica')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->icon('healthicons-f-i-documents-accepted')
                ->extraAttributes(['class' => $cardClass]),
            Stat::make('Casos en Seguimiento', number_format(OperationsDashboardMetrics::followUpCasesCount()))
                ->description('Casos activos en seguimiento médico')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning')
                ->icon('healthicons-f-i-note-action')
                ->extraAttributes(['class' => $cardClass]),
            Stat::make('Proveedores en Operaciones', number_format(OperationsDashboardMetrics::associatedSuppliersCount()))
                ->description('Proveedores con usuarios activos en el panel')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->icon('heroicon-o-building-office-2')
                ->extraAttributes(['class' => $cardClass]),
        ];
    }
}
