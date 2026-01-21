<?php

namespace App\Filament\Marketing\Widgets;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\RrhhColaborador;
use App\Models\TravelAgency;
use Filament\Actions\Action;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Redirect;

class StatsOverviewListGeneral extends StatsOverviewWidget
{
    protected ?string $heading = 'ESTRUTURAS';

    protected ?string $description = 'Resumen general de la Estructura Tu Doctor Group';
    protected function getStats(): array
    {
        return [
            Stat::make('AGENCIAS DE CORRETAJE', Agency::count())
                ->description('Total de Agencia de Corretaje')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    'wire:click' => "\$dispatch('goToListAgencies')",
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('AGENTES DE CORRETAJE', Agent::count())
                ->description('Total de Agentes de Corretaje')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    'wire:click' => "\$dispatch('goToListAgents')",
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('AGENCIAS DE VIAJE', TravelAgency::count())
                ->description('Total de Agencia de Viaje')
                ->descriptionIcon('heroicon-m-map')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    'wire:click' => "\$dispatch('goToListTravelAgencies')",
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('COLABORADORES', RrhhColaborador::count())
                ->description('Total de Colaboradores')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    'wire:click' => "\$dispatch('goToListCollaborators')",
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
        ];
    }

    public function getListeners()
    {
        return [
            'goToListAgencies'          => 'goToListAgencies',
            'goToListAgents'            => 'goToListAgents',
            'goToListTravelAgencies'    => 'goToListTravelAgencies',
            'goToListCollaborators'     => 'goToListCollaborators',
        ];
    }

    public function goToListAgencies()
    {
        return Redirect::to('/marketing/agencies');
    }

    public function goToListAgents()
    {
        return Redirect::to('/marketing/agents');
    }

    public function goToListTravelAgencies()
    {
        return Redirect::to('/marketing/travel-agencies');
    }

    public function goToListCollaborators()
    {
        return Redirect::to('/marketing/rrhh-colaboradors');
    }
}
