<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use App\Models\Sale;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewSales extends StatsOverviewWidget
{

    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE VENTAS POR PLAN';

    protected ?string $description = 'Distribución de ingresos mensuales según el tipo de suscripción.';

    protected function getTablePage(): string
    {
        return ListSales::class;
    }

    protected function getStats(): array
    {
        // dd($this->getPageTableQuery()->where('plan_id', 1)->sum('total_amount'));
        // $start = now()->startOfMonth();
        // $end = now()->endOfMonth();

        // Optimización: Obtenemos los totales agrupados en una sola consulta si fuera necesario, 
        // pero para mantener la claridad del widget Stat::make usamos consultas filtradas.

        return [
            Stat::make('PLAN INICIAL', 'US$ ' . number_format($this->getPageTableQuery()->where('plan_id', 1)->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#9ce1ff] dark:border-[#9ce1ff]',
                    // 'style' => 'background: linear-gradient(135deg, var(--bg-start, #f0fdf4) 0%, var(--bg-end, #ffffff) 100%) !important;',
                ]),

            Stat::make('PLAN IDEAL', 'US$ ' . number_format($this->getPageTableQuery()->where('plan_id', 2)->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),

            Stat::make('PLAN ESPECIAL', 'US$ ' . number_format($this->getPageTableQuery()->where('plan_id', 3)->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#2d89ca] dark:border-[#2d89ca]',
                ]),

            Stat::make('PLAN CORPORATIVO', 'US$ ' . number_format($this->getPageTableQuery()->whereNull('plan_id')->sum('total_amount'), 2, ',', '.'))
                ->description('Ventas del mes actual')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#3b82f6] dark:border-[#3b82f6]',
                ]),
        ];
    }
}
