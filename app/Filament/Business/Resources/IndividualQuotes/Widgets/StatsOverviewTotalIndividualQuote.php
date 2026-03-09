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

        $cardAnio = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardMes = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';

        return [
            Stat::make('Cotizaciones año '.$now->year, number_format($totalAnio))
                ->description('Acumulado del año en curso')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->extraAttributes([
                    'class' => $cardAnio,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('Cotizaciones '.$now->translatedFormat('F'), number_format($totalMes))
                ->description('Emitidas en el mes actual')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success')
                ->extraAttributes([
                    'class' => $cardMes,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
