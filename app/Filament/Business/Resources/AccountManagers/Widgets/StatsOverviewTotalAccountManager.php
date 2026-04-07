<?php

namespace App\Filament\Business\Resources\AccountManagers\Widgets;

use App\Models\AccountManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewTotalAccountManager extends StatsOverviewWidget
{
    protected function getHeading(): ?string
    {
        return 'Resumen de ejecutivos';
    }

    protected function getDescription(): ?string
    {
        return 'Total de account managers registrados en el módulo Business.';
    }

    protected function getStats(): array
    {
        $total = AccountManager::query()->count();

        $cardClass = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';
        $labelClass = 'text-success-600 dark:text-success-400';
        $badgeClass = 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300';

        $description = new HtmlString(<<<HTML
        <div class="flex flex-col mt-1">
            <span class="text-xs font-semibold uppercase tracking-wide {$labelClass}">
                EQUIPO COMERCIAL
            </span>
            <div class="flex items-center gap-2.5 mt-1.5">
                <span class="px-2.5 py-1 text-xs font-bold rounded-lg {$badgeClass} shadow-sm">
                    Account managers activos en sistema
                </span>
            </div>
        </div>
        HTML);

        return [
            Stat::make('TOTAL ACCOUNT MANAGERS', (string) $total)
                ->icon('heroicon-m-globe-alt')
                ->description($description)
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => $cardClass,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
