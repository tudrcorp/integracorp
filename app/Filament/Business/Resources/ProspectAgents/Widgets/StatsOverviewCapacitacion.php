<?php

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Models\ProspectAgent;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewCapacitacion extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $now = Carbon::now();
        $totalProspectos = ProspectAgent::query()->count();
        $prospectosMesActual = ProspectAgent::query()
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $mesActualNombre = $now->translatedFormat('F');

        $iosStyles = '
            group cursor-pointer transition-all duration-500 ease-in-out
            rounded-2xl border border-gray-200/80 dark:border-white/10
            shadow-sm hover:shadow-lg hover:shadow-gray-200/50 dark:hover:shadow-gray-900/30
            hover:scale-[1.02] hover:border-blue-400/40 dark:hover:border-blue-500/30
            antialiased
            [&_*]:transition-all [&_*]:duration-500
            group-hover:[&_.fi-wi-stats-overview-stat-value]:text-blue-600 dark:group-hover:[&_.fi-wi-stats-overview-stat-value]:text-blue-400
        ';

        $descripcionMes = new HtmlString(sprintf(
            '<span class="block text-sm text-gray-500 dark:text-gray-400">Registrados en el sistema</span>'.
            '<span class="mt-3 inline-block rounded-lg bg-blue-500/15 px-2.5 py-1 text-sm font-semibold text-blue-700 dark:bg-blue-400/20 dark:text-blue-300 ring-1 ring-blue-500/20 dark:ring-blue-400/30">%s en %s</span>',
            $prospectosMesActual,
            $mesActualNombre
        ));

        return [
            Stat::make('Total prospectos', $totalProspectos)
                ->description($descripcionMes)
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-m-user-group')
                ->color('primary')
                ->extraAttributes([
                    'class' => $iosStyles,
                ]),
        ];
    }
}
