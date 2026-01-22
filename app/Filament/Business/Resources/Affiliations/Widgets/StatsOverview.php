<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliate;
use App\Models\Affiliation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Afiliados Individuales', Affiliate::where('status', 'ACTIVO')->count() . ' afiliados')
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),
            Stat::make('Total Neto', 'US$ ' . number_format(Affiliation::where('status', 'ACTIVA')->sum('total_amount'), 2, ',', '.'))
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),
        ];
    }
}