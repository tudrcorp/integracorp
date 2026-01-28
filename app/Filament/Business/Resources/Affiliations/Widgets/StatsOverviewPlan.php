<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewPlan extends StatsOverviewWidget
{

    protected ?string $heading = 'ANÁLISIS DE AFILIACIONES POR PLAN';

    protected ?string $description = 'Distribución de afiliaciones mensuales según el tipo de suscripción.';
    
    protected function getStats(): array
    {
        // dd($this->getPageTableQuery()->where('plan_id', 1)->sum('total_amount'));
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        // Optimización: Obtenemos los totales agrupados en una sola consulta si fuera necesario, 
        // pero para mantener la claridad del widget Stat::make usamos consultas filtradas.

        return [
            // Stat::make('PLAN INICIAL', 'US$ ' . number_format($this->getPageTableQuery()->where('plan_id', 1)->sum('total_amount'), 2, ',', '.'))
            Stat::make('PLAN INICIAL', Affiliation::where('plan_id', 1)->whereBetween('created_at', [$start, $end])->count())
                ->description('Mes actual')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),

            Stat::make('PLAN IDEAL', Affiliation::where('plan_id', 2)->whereBetween('created_at', [$start, $end])->count())
                ->description('Mes actual')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),

            Stat::make('PLAN ESPECIAL', Affiliation::where('plan_id', 3)->whereBetween('created_at', [$start, $end])->count())
                ->description('Mes actual')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#2d89ca] dark:border-[#2d89ca]',
                ]),

        ];
    }
}
