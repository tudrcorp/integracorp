<?php

namespace App\Filament\Business\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewSaleBusiness extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'ANÁLISIS DE VENTAS POR PLAN';

    protected ?string $description = 'Ventas del año en curso y del mes en curso.';

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
            ['id' => 1, 'name' => 'PLAN INICIAL', 'icon' => 'heroicon-m-check-badge', 'color' => 'info', 'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500', 'labelClass' => 'text-info-600 dark:text-info-400', 'badgeClass' => 'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300'],
            ['id' => 2, 'name' => 'PLAN IDEAL', 'icon' => 'heroicon-m-star', 'color' => 'primary', 'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500', 'labelClass' => 'text-primary-600 dark:text-primary-400', 'badgeClass' => 'bg-primary-100/90 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300'],
            ['id' => 3, 'name' => 'PLAN ESPECIAL', 'icon' => 'heroicon-m-sparkles', 'color' => 'warning', 'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500', 'labelClass' => 'text-warning-600 dark:text-warning-400', 'badgeClass' => 'bg-warning-100/90 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300'],
            ['id' => 'corp', 'name' => 'PLAN CORPORATIVO', 'icon' => 'heroicon-m-building-office', 'color' => 'success', 'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500', 'labelClass' => 'text-success-600 dark:text-success-400', 'badgeClass' => 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300'],
        ];

        return array_map(function ($plan) use ($startOfYear, $endOfYear, $startOfMonth, $endOfMonth, $nombreMes, $anioActual) {
            $baseQuery = Sale::query()
                ->when($plan['id'] === 'corp', fn ($q) => $q->whereNull('plan_id'), fn ($q) => $q->where('plan_id', $plan['id']));

            $totalAnioActual = (clone $baseQuery)
                ->whereBetween('created_at', [$startOfYear, $endOfYear])
                ->sum('total_amount') ?? 0;

            $totalMesActual = (clone $baseQuery)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('total_amount') ?? 0;

            $valAnio = 'US$ '.number_format($totalAnioActual, 2, ',', '.');
            $valMes = 'US$ '.number_format($totalMesActual, 2, ',', '.');

            return Stat::make($plan['name'], $valAnio)
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-semibold uppercase tracking-wide {$plan['labelClass']}'>
                            TOTAL AÑO {$anioActual}
                        </span>
                        <div class='flex items-center gap-2.5 mt-1.5'>
                            <span class='px-2.5 py-1 text-xs font-bold rounded-lg {$plan['badgeClass']} shadow-sm'>
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
                    'class' => $plan['cardClass'],
                    'style' => 'min-height: 130px;',
                ]);
        }, $plans);
    }
}
