<?php

namespace App\Filament\General\Widgets;

use App\Models\Sale;
use App\Models\Commission;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Afiliaciones Individuales', '+' . Affiliation::where('code_agency', Auth::user()->code_agency)->where('status', 'ACTIVA')->count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Afiliaciones Corporativas', '+' . AffiliationCorporate::where('code_agency', Auth::user()->code_agency)->where('status', 'ACTIVA')->count())
                ->icon('fontisto-persons')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Ventas', 'US$ ' . Sale::where('code_agency', Auth::user()->code_agency)->sum('total_amount'))
                ->icon('fontisto-wallet')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Acumulado de Comisiones', 'US$ ' . Commission::where('code_agency', Auth::user()->code_agency)->sum('total_payment_commission'))
                ->icon('fontisto-shopping-pos-machine')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}