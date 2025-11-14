<?php

namespace App\Filament\Business\Widgets;

use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TwoStatsOverview extends StatsOverviewWidget
{

    protected ?string $heading = 'Estructura Cuantificadas';

    protected ?string $description = 'Número total de Agencias y Agentes.';
    
    
    protected function getStats(): array
    {
        //Si la session en de un administrador de cuentas
        //los stats muestran las estadisticas del administrador, sino muestras las estadisticas del generales
        if (Auth::user()->is_accountManagers == 1) {
            
            $agenciesMaster       = Agency::where('agency_type_id', 1)->where('ownerAccountManagers', Auth::user()->id)->count();
            $agenciesGeneral      = Agency::where('agency_type_id', 3)->where('ownerAccountManagers', Auth::user()->id)->count();
            $agents               = Agent::where('ownerAccountManagers', Auth::user()->id)->count();
            
        } else {

            $agenciesMaster       = Agency::where('agency_type_id', 1)->count();
            $agenciesGeneral      = Agency::where('agency_type_id', 3)->count();
            $agents               = Agent::count();
            $accountManagers      = User::where('is_accountManagers', 1)->count();
            
        }
        
        //Si el usuario logueado es un administrador debe ver todos los stats de estadisticas
        if(Auth::user()->is_business_admin == 1){
            return [
                Stat::make('AGENCIAS MASTER', $agenciesMaster)
                    ->icon('fontisto-person')
                    ->description('Incremento')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success')
                    ->extraAttributes([
                        'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                    ]),
                Stat::make('AGENCIAS GENERALES', $agenciesGeneral)
                    ->icon('fontisto-person')
                    ->description('Incremento')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success')
                    ->extraAttributes([
                        'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                    ]),
                Stat::make('AGENTES', $agents)
                    ->icon('fontisto-person')
                    ->description('Incremento')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success')
                    ->extraAttributes([
                        'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                    ]),
                Stat::make('ACCOUNT MANAGERS', $accountManagers)
                    ->icon('fontisto-person')
                    ->description('Incremento')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success')
                    ->extraAttributes([
                        'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                    ]),
            ];
            
        }

        //Si el usuario logueado es un administrador de cuentas debe ver solo las estadisticas de agencias y agentes
        return [
            Stat::make('AGENCIAS MASTER', $agenciesMaster)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('AGENCIAS GENERALES', $agenciesGeneral)
                ->icon('fontisto-person')
                ->description('Incremento')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'border-2 border-[#00a7d1] font-bold text-white',
                ]),
            Stat::make('AGENTES', $agents)
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