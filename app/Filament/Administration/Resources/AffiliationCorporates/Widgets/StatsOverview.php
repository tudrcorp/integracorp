<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\Widgets;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Corporativos', AffiliationCorporate::where('status', 'ACTIVA')->count() . ' empresas')
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Afiliados Corporativos', AffiliateCorporate::where('status', 'ACTIVO')->count().' afiliados')
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make('Total Neto', 'US$ '.number_format(AffiliationCorporate::all()->sum('total_amount'), 2, ',', '.'))
                ->icon('heroicon-m-user-group')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
        ];
    }
}