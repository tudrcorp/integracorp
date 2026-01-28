<?php

namespace App\Filament\Business\Resources\AccountManagers\Widgets;

use App\Models\Agency;
use App\Models\Agent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class StatsOverviewCountAgentAgency extends StatsOverviewWidget
{
    public ?Model $record = null;
    protected function getStats(): array
    {
        return [
            Stat::make('AGENCIAS', Agency::where('ownerAccountManagers', $this->record->user_id)->count())
                ->description('Total de Agencias Asignadas al Ejecutivo')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
            Stat::make('AGENTES', Agent::where('ownerAccountManagers', $this->record->user_id)->count())
                ->description('Total de Agentes Asignados al Ejecutivo')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),
        ];
    }
}
