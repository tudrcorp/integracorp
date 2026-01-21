<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewSales extends StatsOverviewWidget
{
    protected ?string $heading = 'ANÁLISIS DE VENTAS POR PLAN';

    protected ?string $description = 'Distribución de ingresos mensuales según el tipo de suscripción.';

    protected function getStats(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        // Optimización: Obtenemos los totales agrupados en una sola consulta si fuera necesario, 
        // pero para mantener la claridad del widget Stat::make usamos consultas filtradas.

        return [
            Stat::make('PLAN INICIAL', 'US$ ' . number_format(Sale::where('plan_id', 1)->whereBetween('created_at', [$start, $end])->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl shadow-sm border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),

            Stat::make('PLAN IDEAL', 'US$ ' . number_format(Sale::where('plan_id', 2)->whereBetween('created_at', [$start, $end])->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl shadow-sm border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),

            Stat::make('PLAN ESPECIAL', 'US$ ' . number_format(Sale::where('plan_id', 3)->whereBetween('created_at', [$start, $end])->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl shadow-sm border-b-4 border-[#2d89ca] dark:border-[#2d89ca]',
                ]),

            Stat::make('PLAN CORPORATIVO', 'US$ ' . number_format(Sale::whereNull('plan_id')->whereBetween('created_at', [$start, $end])->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl shadow-sm border-b-4 border-[#3b82f6] dark:border-[#3b82f6]',
                ]),
        ];
    }
}
