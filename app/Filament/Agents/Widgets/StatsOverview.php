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
                ->icon('heroicon-o-document-text')
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Afiliaciones Corporativas', '+' .AffiliationCorporate::where('agent_id', Auth::user()->agent_id)->where('status', 'ACTIVA')->count())
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),
            Stat::make('Total Ventas', 'US$ '. Sale::where('agent_id', Auth::user()->agent_id)->sum('total_amount'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
            Stat::make('Acumulado de Comisiones', 'US$ ' . Commission::where('agent_id', Auth::user()->agent_id)->sum('total_payment_commission'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('gray'),
        ];
    }
}