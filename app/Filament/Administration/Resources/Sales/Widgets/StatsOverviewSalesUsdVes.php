<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class StatsOverviewSalesUsdVes extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE INGRESOS';

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

        // Definición de métricas
        $metrics = [
            [
                'id' => 'usd',
                'label' => 'VENTAS TOTALES USD',
                'column' => 'total_amount',
                'symbol' => 'US$',
                'icon' => 'heroicon-m-currency-dollar',
                'color' => 'info', // Azul
                'is_payment_link' => false,
            ],
            [
                'id' => 'ves',
                'label' => 'VENTAS TOTALES VES',
                'column' => 'pay_amount_ves',
                'symbol' => 'Bs.',
                'icon' => 'heroicon-m-banknotes',
                'color' => 'success', // Verde
                'is_payment_link' => false,
            ],
            [
                'id' => 'link',
                'label' => 'VENTAS TOTALES LINK DE PAGO',
                'column' => 'pay_amount_usd',
                'symbol' => 'US$',
                'icon' => 'heroicon-o-link',
                'color' => 'success', // Verde
                'is_payment_link' => true,
            ],
        ];

        return array_map(function ($metric) use ($startOfYear, $endOfYear, $startOfMonth, $endOfMonth, $nombreMes, $anioActual) {
            // Query base con filtros de la tabla aplicados (InteractsWithPageTable)
            $baseQuery = fn () => (clone $this->getPageTableQuery())->where('is_payment_link', $metric['is_payment_link']);

            // 1. Total año en curso (respeta filtros de la tabla)
            $totalAnioActual = $baseQuery()
                ->whereBetween('created_at', [$startOfYear, $endOfYear])
                ->sum($metric['column']) ?? 0;

            // 2. Total mes en curso (respeta filtros de la tabla)
            $totalMesActual = $baseQuery()
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum($metric['column']) ?? 0;

            $valAnio = $metric['symbol'].' '.number_format($totalAnioActual, 2, ',', '.');
            $valMes = $metric['symbol'].' '.number_format($totalMesActual, 2, ',', '.');

            return Stat::make($metric['label'], $valAnio)
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
                ->descriptionIcon($metric['icon'])
                ->color($metric['color'])
                ->extraAttributes([
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-orange-200/60 dark:border-orange-700/50 bg-gradient-to-br from-orange-50/90 via-white to-orange-50/50 dark:from-orange-950/40 dark:via-gray-900/80 dark:to-orange-900/20 hover:shadow-lg hover:shadow-orange-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-orange-400/50 hover:border-orange-300 dark:hover:border-orange-500',
                    'style' => 'min-height: 130px;',
                ]);
        }, $metrics);
    }
}
