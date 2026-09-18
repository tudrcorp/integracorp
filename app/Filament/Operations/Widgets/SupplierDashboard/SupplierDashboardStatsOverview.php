<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Support\Operations\SupplierDashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupplierDashboardStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $cardClass = 'overflow-hidden rounded-2xl border border-slate-200/70 bg-gradient-to-br from-white via-slate-50/90 to-white shadow-[0_12px_40px_-16px_rgba(15,23,42,0.14)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[0_18px_48px_-18px_rgba(15,23,42,0.22)] dark:border-white/10 dark:from-slate-900/90 dark:via-slate-950/95 dark:to-slate-900/90';

        return [
            Stat::make('Analistas Registrados', number_format(SupplierDashboardMetrics::analystsCount()))
                ->description('Usuarios activos con acceso al panel')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->icon('heroicon-o-identification')
                ->extraAttributes(['class' => $cardClass]),
            Stat::make('Doctores Registrados', number_format(SupplierDashboardMetrics::doctorsCount()))
                ->description('Médicos acreditados en telemedicina')
                ->descriptionIcon('heroicon-m-heart')
                ->color('info')
                ->icon('healthicons-f-doctor')
                ->extraAttributes(['class' => $cardClass]),
            Stat::make('Casos en Seguimiento', number_format(SupplierDashboardMetrics::followUpCasesCount()))
                ->description('Casos activos bajo control médico')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning')
                ->icon('healthicons-f-i-note-action')
                ->extraAttributes(['class' => $cardClass]),
            Stat::make('Casos de Alta Médica', number_format(SupplierDashboardMetrics::medicalDischargeCasesCount()))
                ->description('Casos cerrados con alta médica')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->icon('healthicons-f-i-documents-accepted')
                ->extraAttributes(['class' => $cardClass]),
        ];
    }
}
