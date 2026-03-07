<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use Filament\Widgets\Concerns\InteractsWithPageTable;
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
                ->descriptionIcon($metric['icon'])
                ->color($metric['color'])
                ->extraAttributes([
                    'class' => 'cursor-default transition-all duration-300 hover:ring-2 hover:ring-primary-500',
                    'style' => 'border-radius: 16px; min-height: 120px;',
                ]);
        }, $metrics);
    }
}
