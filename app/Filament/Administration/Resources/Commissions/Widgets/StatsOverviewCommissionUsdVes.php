<?php

namespace App\Filament\Administration\Resources\Commissions\Widgets;

use App\Filament\Administration\Resources\Commissions\Pages\ListCommissions;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class StatsOverviewCommissionUsdVes extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE COMISIONES';

    protected function getTablePage(): string
    {
        return ListCommissions::class;
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

        $metrics = [
            [
                'label' => 'COMISIONES TOTALES USD',
                'columns' => ['commission_agency_master_usd', 'commission_agency_general_usd', 'commission_agent_usd'],
                'symbol' => 'US$',
                'icon' => 'heroicon-m-currency-dollar',
                'color' => 'info',
            ],
            [
                'label' => 'COMISIONES TOTALES VES',
                'columns' => ['commission_agency_master_ves', 'commission_agency_general_ves', 'commission_agent_ves'],
                'symbol' => 'Bs.',
                'icon' => 'heroicon-m-banknotes',
                'color' => 'success',
            ],
        ];

        return array_map(function ($metric) use ($startOfYear, $endOfYear, $startOfMonth, $endOfMonth, $nombreMes, $anioActual) {
            $baseQuery = fn () => clone $this->getPageTableQuery();

            $sumExpression = implode(' + ', array_map(fn ($col) => "COALESCE({$col}, 0)", $metric['columns']));

            $totalAnioActual = $baseQuery()
                ->whereBetween('created_at', [$startOfYear, $endOfYear])
                ->selectRaw("SUM({$sumExpression}) as total")
                ->value('total') ?? 0;

            $totalMesActual = $baseQuery()
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->selectRaw("SUM({$sumExpression}) as total")
                ->value('total') ?? 0;

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
