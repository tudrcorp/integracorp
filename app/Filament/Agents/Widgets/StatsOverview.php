<?php

namespace App\Filament\Agents\Widgets;

use App\Models\Sale;
use App\Models\Commission;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        return [
            Stat::make('Afiliaciones Individuales', '+'.Affiliation::where('agent_id', Auth::user()->agent_id)->where('status', 'ACTIVA')->count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Afiliaciones Corporativas', '+' .AffiliationCorporate::where('agent_id', Auth::user()->agent_id)->where('status', 'ACTIVA')->count())
                ->icon('fontisto-persons')    
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Ventas', 'US$ '. Sale::where('agent_id', Auth::user()->agent_id)->sum('total_amount'))
                ->icon('fontisto-wallet')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Acumulado de Comisiones', 'US$ ' . Commission::where('agent_id', Auth::user()->agent_id)->sum('total_payment_commission'))
                ->icon('fontisto-shopping-pos-machine')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}