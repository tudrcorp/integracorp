<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Models\IndividualQuote;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewTotalIndividualQuote extends StatsOverviewWidget
{
    protected ?string $heading = 'Total de cotizaciones';

    protected ?string $description = 'Resumen por año en curso y mes actual.';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $totalAnio = IndividualQuote::query()
            ->whereYear('created_at', $now->year)
            ->count();
        $totalMes = IndividualQuote::query()
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $colors = ['#3b82f6', '#10b981', '#f59e0b'];

        $iosCardBase = '
            group cursor-default overflow-hidden
            rounded-2xl antialiased
            bg-white/90 dark:bg-gray-900/90
            ring-1 ring-gray-200/60 dark:ring-white/10
            shadow-lg shadow-gray-200/50 dark:shadow-black/20
            transition-all duration-500 ease-out
            hover:scale-[1.02] hover:shadow-xl
            [&_*]:transition-all [&_*]:duration-500
        ';

        $iosCardAnio = $iosCardBase.'
            border-b-4 border-[#3b82f6]/60 dark:border-[#3b82f6]/50
            hover:border-[#3b82f6] hover:bg-[#3b82f6]/10 dark:hover:bg-[#3b82f6]/15
            hover:ring-2 hover:ring-[#3b82f6]/30 dark:hover:ring-[#3b82f6]/40
            hover:shadow-[0_8px_30px_rgba(59,130,246,0.25)] dark:hover:shadow-[0_8px_30px_rgba(59,130,246,0.2)]
            group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#3b82f6] dark:group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#60a5fa]
        ';

        $iosCardMes = $iosCardBase.'
            border-b-4 border-[#10b981]/60 dark:border-[#10b981]/50
            hover:border-[#10b981] hover:bg-[#10b981]/10 dark:hover:bg-[#10b981]/15
            hover:ring-2 hover:ring-[#10b981]/30 dark:hover:ring-[#10b981]/40
            hover:shadow-[0_8px_30px_rgba(16,185,129,0.25)] dark:hover:shadow-[0_8px_30px_rgba(16,185,129,0.2)]
            group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#10b981] dark:group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#34d399]
        ';

        return [
            Stat::make('Cotizaciones año '.$now->year, number_format($totalAnio))
                ->description('Acumulado del año en curso')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->extraAttributes(['class' => $iosCardAnio]),

            Stat::make('Cotizaciones '.$now->translatedFormat('F'), number_format($totalMes))
                ->description('Emitidas en el mes actual')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success')
                ->extraAttributes(['class' => $iosCardMes]),
        ];
    }
}
