<?php

namespace App\Filament\Agents\Widgets;

use App\Models\Sale;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Commission;
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
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),
            Stat::make('Afiliaciones Corporativas', '+' .AffiliationCorporate::where('agent_id', Auth::user()->agent_id)->where('status', 'ACTIVA')->count())
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('warning'),
            Stat::make('total Ventas', 'US$ '. Sale::where('agent_id', Auth::user()->agent_id)->sum('total_amount'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('primary'),
            Stat::make('Acumulado de Comisiones', 'US$ ' . Commission::where('agent_id', Auth::user()->agent_id)->sum('total_payment_commission'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('gray'),
        ];
    }
}