<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class StatsOverviewSales extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE VENTAS POR PLAN';

    protected function getTablePage(): string
    {
        return ListSales::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $nombreMes = ucfirst($now->translatedFormat('F'));
        $anioActual = $now->year;

        $plans = [
            ['id' => 1, 'name' => 'PLAN INICIAL', 'icon' => 'heroicon-m-check-badge', 'color' => 'info'],
            ['id' => 2, 'name' => 'PLAN IDEAL', 'icon' => 'heroicon-m-star', 'color' => 'primary'],
            ['id' => 3, 'name' => 'PLAN ESPECIAL', 'icon' => 'heroicon-m-sparkles', 'color' => 'warning'],
            ['id' => 'corp', 'name' => 'PLAN CORPORATIVO', 'icon' => 'heroicon-m-building-office', 'color' => 'success'],
        ];

        return array_map(function ($plan) use ($startOfYear, $endOfYear, $startOfMonth, $endOfMonth, $nombreMes, $anioActual) {
            $baseQuery = function () use ($plan) {
                $query = clone $this->getPageTableQuery();
                if ($plan['id'] === 'corp') {
                    $query->whereNull('plan_id');
                } else {
                    $query->where('plan_id', $plan['id']);
                }

                return $query;
            };

            $totalAnioActual = $baseQuery()
                ->whereBetween('created_at', [$startOfYear, $endOfYear])
                ->sum('total_amount') ?? 0;

            $totalMesActual = $baseQuery()
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('total_amount') ?? 0;

            $valAnio = 'US$ '.number_format($totalAnioActual, 2, ',', '.');
            $valMes = 'US$ '.number_format($totalMesActual, 2, ',', '.');

            return Stat::make($plan['name'], $valAnio)
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-medium text-gray-500 dark:text-gray-400'>
                            TOTAL AÑO {$anioActual}
                        </span>
                        <div class='flex items-center gap-2.5 mt-1'>
                            <span class='px-2 py-0.5 text-xs font-bold rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400'>
                                Mes actual ({$nombreMes}):
                            </span>
                            <span class='text-sm font-bold text-gray-900 dark:text-white'>
                                {$valMes}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon($plan['icon'])
                ->color($plan['color'])
                ->extraAttributes([
                    'class' => 'cursor-default transition-all duration-300 hover:ring-2 hover:ring-primary-500',
                    'style' => 'border-radius: 16px; min-height: 120px;',
                ]);
        }, $plans);
    }
}
