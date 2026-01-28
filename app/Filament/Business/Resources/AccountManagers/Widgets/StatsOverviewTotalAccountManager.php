<?php

namespace App\Filament\Business\Resources\AccountManagers\Widgets;

use App\Models\AccountManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewTotalAccountManager extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('TOTAL ACCOUNT MANAGER', AccountManager::count())
                ->description('Total de Ejecutivos')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
        ];
    }
}
