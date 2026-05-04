<?php

namespace App\Filament\Business\Resources\Helpdesks\Widgets;

use App\Filament\Business\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewHelpdesk extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListHelpdesks::class;
    }

    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $baseQuery = $this->getPageTableQuery();

        $totalTickets = (clone $baseQuery)->count();
        $pendientePorIniciar = (clone $baseQuery)->where('status', 'PENDIENTE POR INICIAR')->count();
        $enProceso = (clone $baseQuery)->where('status', 'EN PROCESO')->count();
        $terminado = (clone $baseQuery)->where('status', 'TERMINADO')->count();

        $cardTotal = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';
        $cardPendiente = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500';
        $cardProceso = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';
        $cardTerminado = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';

        return [
            Stat::make('TOTAL DE TICKETS', (string) $totalTickets)
                ->icon('heroicon-m-ticket')
                ->description('Tickets visibles en este listado')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => $cardTotal,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('PENDIENTE POR INICIAR', (string) $pendientePorIniciar)
                ->icon('heroicon-m-clock')
                ->description('Aun sin iniciar gestion')
                ->color('warning')
                ->extraAttributes([
                    'class' => $cardPendiente,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('EN PROCESO', (string) $enProceso)
                ->icon('heroicon-m-arrow-path')
                ->description('Tickets actualmente en atencion')
                ->color('primary')
                ->extraAttributes([
                    'class' => $cardProceso,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('TERMINADO', (string) $terminado)
                ->icon('heroicon-m-check-circle')
                ->description('Tickets cerrados exitosamente')
                ->color('success')
                ->extraAttributes([
                    'class' => $cardTerminado,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
