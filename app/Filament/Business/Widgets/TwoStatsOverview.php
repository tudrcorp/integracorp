<?php

namespace App\Filament\Business\Widgets;

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
                ->color('success'),
            Stat::make('AGENCIAS GENERALES', Agency::where('agency_type_id', 3)->count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('AGENTES', Agent::count())
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}