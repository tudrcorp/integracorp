<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\Widgets;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $now = Carbon::now();
        $nombreMes = ucfirst($now->translatedFormat('F'));
        $anioActual = (int) $now->year;

        $cardClass = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-orange-200/60 dark:border-orange-700/50 bg-gradient-to-br from-orange-50/90 via-white to-orange-50/50 dark:from-orange-950/40 dark:via-gray-900/80 dark:to-orange-900/20 hover:shadow-lg hover:shadow-orange-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-orange-400/50 hover:border-orange-300 dark:hover:border-orange-500';

        return [
            Stat::make('Total Corporativos', AffiliationCorporate::where('status', 'ACTIVA')->count().' empresas')
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->extraAttributes([
                    'class' => $cardClass,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('Total Afiliados Corporativos', AffiliateCorporate::where('status', 'ACTIVO')->count().' afiliados')
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->extraAttributes([
                    'class' => $cardClass,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('Total Neto', 'US$ '.number_format(AffiliationCorporate::all()->sum('total_amount'), 2, ',', '.'))
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->extraAttributes([
                    'class' => $cardClass,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
