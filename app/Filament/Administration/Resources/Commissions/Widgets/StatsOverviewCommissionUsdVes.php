<?php

namespace App\Filament\Administration\Resources\Commissions\Widgets;

use App\Filament\Administration\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
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
            $sumExpression = implode(' + ', array_map(fn ($col) => "COALESCE({$col}, 0)", $metric['columns']));

            $queryForSum = function (?\DateTimeInterface $from, ?\DateTimeInterface $to) {
                $query = clone $this->getPageTableQuery();
                $query->reorder();
                $query->getQuery()->limit = null;
                $query->getQuery()->offset = null;

                return $query->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]));
            };

            $totalAnioActual = $queryForSum($startOfYear, $endOfYear)
                ->selectRaw("SUM({$sumExpression}) as total")
                ->value('total') ?? 0;

            $totalMesActual = $queryForSum($startOfMonth, $endOfMonth)
                ->selectRaw("SUM({$sumExpression}) as total")
                ->value('total') ?? 0;

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
