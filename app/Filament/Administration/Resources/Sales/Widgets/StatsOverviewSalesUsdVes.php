<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use App\Models\Sale;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class StatsOverviewSalesUsdVes extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListSales::class;
    }

    public ?Model $record = null;

    protected ?string $heading = 'ANÁLISIS DE VENTAS';

    protected ?string $description = 'Comparativa de ingresos netos y métodos de pago en divisas.';



    // protected function getStats(): array
    // {
    //     // Rango de fechas: Mes actual
    //     $start = now()->startOfMonth();
    //     $end = now()->endOfMonth();

    //     // Consultas optimizadas
    //     $ventaNeta = Sale::whereBetween('created_at', [$start, $end])->sum('total_amount');
    //     $pagosUsd = Sale::whereBetween('created_at', [$start, $end])->sum('pay_amount_usd');
    //     $pagosVes = Sale::whereBetween('created_at', [$start, $end])->sum('pay_amount_ves');

    //     return [
    //         Stat::make('VENTA NETA', 'US$ ' . number_format($ventaNeta, 2, ',', '.'))
    //             ->description('Total facturado este mes')
    //             ->descriptionIcon('heroicon-m-banknotes')
    //             ->color('success')
    //             ->extraAttributes([
    //                 'class' => 'cursor-pointer hover:scale-[1.02] transition-transform border-b-4 border-emerald-500 dark:border-emerald-400',
    //                 // 'style' => 'background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%) !important;',
    //             ]),

    //         Stat::make('PAGOS EN DÓLARES', 'US$ ' . number_format($pagosUsd, 2, ',', '.'))
    //             ->description('Ingresos en divisas (Efectivo/Zelle/Transferencia)')
    //             ->descriptionIcon('heroicon-m-currency-dollar')
    //             ->color('info')
    //             ->extraAttributes([
    //                 'class' => 'cursor-pointer hover:scale-[1.02] transition-transform border-b-4 border-blue-500',
    //                 // 'style' => 'background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%) !important;',
    //             ]),

    //         Stat::make('PAGOS EN BOLÍVARES', 'VES ' . number_format($pagosVes, 2, ',', '.'))
    //             ->description('Ingresos en moneda local')
    //             ->descriptionIcon('heroicon-m-credit-card')
    //             ->color('warning')
    //             ->extraAttributes([
    //                 'class' => 'cursor-pointer hover:scale-[1.02] transition-transform border-b-4 border-amber-500',
    //                 // 'style' => 'background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%) !important;',
    //             ]),
    //     ];
    // }

    /**
     * SOLUCIÓN AL ERROR:
     * Se cambia getTableQuery() por getPageTableQuery(), que es el método 
     * proporcionado por el trait InteractsWithPageTable para acceder a la query filtrada.
     */
    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();

        return [
            Stat::make('VENTA NETA', 'US$ ' . number_format((clone $query)->sum('total_amount'), 2, ',', '.'))
                ->description('Total facturado según filtros')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-transform border-b-4 border-emerald-500 dark:border-emerald-400',
                    // 'style' => 'background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%) !important;',
                ]),

            Stat::make('PAGOS EN DÓLARES', 'US$ ' . number_format((clone $query)->sum('pay_amount_usd'), 2, ',', '.'))
                ->description('Ingresos en divisas (Efectivo/Zelle/Transferencia)')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-transform border-b-4 border-blue-500',
                    // 'style' => 'background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%) !important;',
                ]),

            Stat::make('PAGOS EN BOLÍVARES', 'VES ' . number_format((clone $query)->sum('pay_amount_ves'), 2, ',', '.'))
                ->description('Ingresos en moneda local')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-transform border-b-4 border-amber-500',
                    // 'style' => 'background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%) !important;',
                ]),
        ];
    }
}
