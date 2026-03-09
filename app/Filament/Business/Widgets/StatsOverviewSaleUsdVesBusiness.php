<?php

namespace App\Filament\Business\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewSaleUsdVesBusiness extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'ANÁLISIS DE INGRESOS';

    protected ?string $description = 'Ventas del año en curso y del mes en curso (USD, VES y link de pago).';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $nombreMes = ucfirst($now->translatedFormat('F'));
        $anioActual = $now->year;

        $metrics = [
            [
                'id' => 'usd',
                'label' => 'VENTAS TOTALES USD',
                'column' => 'total_amount',
                'symbol' => 'US$',
                'icon' => 'heroicon-m-currency-dollar',
                'color' => 'info',
                'is_payment_link' => false,
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500',
                'labelClass' => 'text-info-600 dark:text-info-400',
                'badgeClass' => 'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300',
            ],
            [
                'id' => 'ves',
                'label' => 'VENTAS TOTALES VES',
                'column' => 'pay_amount_ves',
                'symbol' => 'Bs.',
                'icon' => 'heroicon-m-banknotes',
                'color' => 'success',
                'is_payment_link' => false,
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                'labelClass' => 'text-success-600 dark:text-success-400',
                'badgeClass' => 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300',
            ],
            [
                'id' => 'link',
                'label' => 'VENTAS TOTALES LINK DE PAGO',
                'column' => 'pay_amount_usd',
                'symbol' => 'US$',
                'icon' => 'heroicon-o-link',
                'color' => 'warning',
                'is_payment_link' => true,
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500',
                'labelClass' => 'text-warning-600 dark:text-warning-400',
                'badgeClass' => 'bg-warning-100/90 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300',
            ],
        ];

        return array_map(function ($metric) use ($startOfYear, $endOfYear, $startOfMonth, $endOfMonth, $nombreMes, $anioActual) {
            $baseQuery = Sale::query()->where('is_payment_link', $metric['is_payment_link']);

            $totalAnioActual = (clone $baseQuery)
                ->whereBetween('created_at', [$startOfYear, $endOfYear])
                ->sum($metric['column']) ?? 0;

            $totalMesActual = (clone $baseQuery)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum($metric['column']) ?? 0;

            $valAnio = $metric['symbol'].' '.number_format($totalAnioActual, 2, ',', '.');
            $valMes = $metric['symbol'].' '.number_format($totalMesActual, 2, ',', '.');

            return Stat::make($metric['label'], $valAnio)
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-semibold uppercase tracking-wide {$metric['labelClass']}'>
                            TOTAL AÑO {$anioActual}
                        </span>
                        <div class='flex items-center gap-2.5 mt-1.5'>
                            <span class='px-2.5 py-1 text-xs font-bold rounded-lg {$metric['badgeClass']} shadow-sm'>
                                Mes actual ({$nombreMes}):
                            </span>
                            <span class='text-sm font-bold text-gray-900 dark:text-white'>
                                {$valMes}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon($metric['icon'])
                ->color($metric['color'])
                ->extraAttributes([
                    'class' => $metric['cardClass'],
                    'style' => 'min-height: 130px;',
                ]);
        }, $metrics);
    }
}
