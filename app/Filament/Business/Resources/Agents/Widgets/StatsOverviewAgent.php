<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Business\Resources\Agents\Pages\ListAgents;
use App\Models\Agent;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewAgent extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Panel de Control de Red';

    protected ?string $description = 'Métricas clave de Agencias y Agentes.';

    protected function getTablePage(): string
    {
        return ListAgents::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $nombreMes = ucfirst($now->translatedFormat('F'));

        // --- LÓGICA DE AGENTES ---
        // Se calculan los agentes basados en el estado global
        $totalAgentes = Agent::count();
        $agentesActivos = Agent::where('status', 'ACTIVO')->count();
        $agentesInactivos = Agent::where('status', 'INACTIVO')->count();

        // Estilo común para las tarjetas tipo iOS
        $iosCardStyles = '
            relative overflow-hidden border-none shadow-sm transition-all duration-300 
            hover:shadow-md hover:-translate-y-1 group 
            bg-white/70 dark:bg-gray-900/50 backdrop-blur-xl 
            ring-1 ring-gray-200 dark:ring-white/10
        ';

        return [

            // Card 2: Agentes (Nueva sección solicitada)
            Stat::make('TOTAL AGENTES', $totalAgentes)
                ->description(new HtmlString("
                    <div class='mt-3 space-y-2'>
                        <div class='flex items-center justify-between text-[10px] uppercase tracking-widest font-semibold text-gray-400 dark:text-gray-500'>
                            <span>Estatus de Agentes</span>
                        </div>
                        <div class='flex items-center gap-3 p-2 rounded-2xl bg-blue-50/50 dark:bg-blue-500/5'>
                            <div class='flex flex-col flex-1'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]'></div>
                                    <span class='text-xs font-medium text-gray-600 dark:text-gray-300'>Activos</span>
                                </div>
                                <span class='text-lg font-bold tracking-tight text-gray-900 dark:text-white'>{$agentesActivos}</span>
                            </div>
                            <div class='w-px h-8 bg-blue-200 dark:bg-white/10'></div>
                            <div class='flex flex-col flex-1'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='w-1.5 h-1.5 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]'></div>
                                    <span class='text-xs font-medium text-gray-600 dark:text-gray-300'>Inactivos</span>
                                </div>
                                <span class='text-lg font-bold tracking-tight text-gray-900 dark:text-white'>{$agentesInactivos}</span>
                            </div>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-users')
                ->extraAttributes([
                    'class' => $iosCardStyles,
                    'style' => 'border-radius: 24px;',
                ]),
        ];
    }
}
