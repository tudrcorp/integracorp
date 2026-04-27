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
                        <span class='text-xs font-semibold uppercase tracking-wide text-orange-600 dark:text-orange-400'>
                            TOTAL AÑO {$anioActual}
                        </span>
                        <div class='flex items-center gap-2.5 mt-1.5'>
                            <span class='px-2.5 py-1 text-xs font-bold rounded-lg bg-orange-100/90 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300 shadow-sm'>
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
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-orange-200/60 dark:border-orange-700/50 bg-gradient-to-br from-orange-50/90 via-white to-orange-50/50 dark:from-orange-950/40 dark:via-gray-900/80 dark:to-orange-900/20 hover:shadow-lg hover:shadow-orange-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-orange-400/50 hover:border-orange-300 dark:hover:border-orange-500',
                    'style' => 'min-height: 130px;',
                ]);
        }, $plans);
    }
}
