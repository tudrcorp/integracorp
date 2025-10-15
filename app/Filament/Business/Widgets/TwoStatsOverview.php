<?php

namespace App\Filament\Business\Widgets;

use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TwoStatsOverview extends StatsOverviewWidget
{

    protected ?string $heading = 'Estructura Cuantificadas';

    protected ?string $description = 'Número total de Agencias y Agentes.';
    
    
    protected function getStats(): array
    {
        return [
            Stat::make('AGENCIAS MASTER', Agency::where('agency_type_id',1)->count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('AGENCIAS GENERALES', Agency::where('agency_type_id', 3)->count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('AGENTES', Agent::count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('ACCOUNT MANAGERS', User::where('is_accountManagers', true)->count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
        ];
    }
}