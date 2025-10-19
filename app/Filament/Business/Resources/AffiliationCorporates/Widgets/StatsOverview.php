<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Corporativos', AffiliationCorporate::all()->count() . ' empresas')
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Afiliados Corporativos', AffiliateCorporate::all()->count().' afiliados')
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make('Total Neto', 'US$ '.AffiliationCorporate::all()->sum('total_amount'))
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
        ];
    }
}