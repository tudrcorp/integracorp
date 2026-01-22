<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Models\IndividualQuote;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewIndividualQuoteAprobada extends StatsOverviewWidget
{
    protected ?string $heading = 'ANÁLISIS DE REGISTROS DE COTIZACIÓN INDIVIDUAL EJECUTADAS';

    protected ?string $description = 'Distribución de cotizaciones mensuales según el tipo de suscripción.';

    protected function getTablePage(): string
    {
        return ListIndividualQuotes::class;
    }

    protected function getStats(): array
    {
        // dd($this->getPageTableQuery()->where('plan_id', 1)->sum('total_amount'));
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        // Optimización: Obtenemos los totales agrupados en una sola consulta si fuera necesario, 
        // pero para mantener la claridad del widget Stat::make usamos consultas filtradas.

        return [
            // Stat::make('PLAN INICIAL', 'US$ ' . number_format($this->getPageTableQuery()->where('plan_id', 1)->sum('total_amount'), 2, ',', '.'))
            Stat::make('COTIZACIONES PLAN INICIAL', IndividualQuote::where('plan', 1)->where('status', 'EJECUTADA')->whereBetween('created_at', [$start, $end])->count())
                ->description('Mes actual')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),

            Stat::make('COTIZACIONES PLAN IDEAL', IndividualQuote::where('plan', 2)->where('status', 'EJECUTADA')->whereBetween('created_at', [$start, $end])->count())
                ->description('Mes actual')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),

            Stat::make('COTIZACIONES PLAN ESPECIAL', IndividualQuote::where('plan', 3)->where('status', 'EJECUTADA')->whereBetween('created_at', [$start, $end])->count())
                ->description('Mes actual')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#2d89ca] dark:border-[#2d89ca]',
                ]),

            Stat::make('COTIZACIONES MULTIPLAN', IndividualQuote::where('plan', 'CM')->where('status', 'EJECUTADA')->whereBetween('created_at', [$start, $end])->count())
                ->description('Mes actual')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#3b82f6] dark:border-[#3b82f6]',
                ]),


        ];
    }
}
