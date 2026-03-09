<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliations::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        // --- CÁLCULOS PARA AFILIADOS INDIVIDUALES ---
        $totalAfiliados = $this->getPageTableQuery()->where('status', 'ACTIVA')->count();
        $totalAfiliadosMes = $this->getPageTableQuery()
            ->where('status', 'ACTIVA')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        // --- CÁLCULOS PARA TOTAL NETO ---
        $totalNeto = $this->getPageTableQuery()->where('status', 'ACTIVA')->sum('total_amount');
        $totalNetoMes = $this->getPageTableQuery()
            ->where('status', 'ACTIVA')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('total_amount');

        $cardAfiliados = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardNeto = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';

        return [
            Stat::make('Total Afiliados Individuales', $totalAfiliados.' Afiliados')
                ->icon('heroicon-m-user-group')
                ->description('Con Planes Individuales')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardAfiliados,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$totalAfiliados} Afiliados', desc: 'Con Planes Individuales' }",
                    '@mouseenter' => "label = '{$totalAfiliadosMes} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$totalAfiliados} Afiliados'; desc = 'Con Planes Individuales'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$totalAfiliados} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Con Planes Individuales</span>")),

            Stat::make('Total Neto', 'US$ '.number_format($totalNeto, 2, ',', '.'))
                ->icon('heroicon-m-currency-dollar')
                ->description('Total Neto Cuantificable')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $cardNeto,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{
                        valor: 'US$ ".number_format($totalNeto, 2, ',', '.')."',
                        desc: 'Total Neto Cuantificable'
                    }",
                    '@mouseenter' => "
                        valor = 'US$ ".number_format($totalNetoMes, 2, ',', '.')."';
                        desc = 'Recaudado en ".$mesActualNombre."';
                    ",
                    '@mouseleave' => "
                        valor = 'US$ ".number_format($totalNeto, 2, ',', '.')."';
                        desc = 'Total Neto Cuantificable';
                    ",
                ])
                ->value(new HtmlString("<span x-text='valor'>US$ ".number_format($totalNeto, 2, ',', '.').'</span>'))
                ->description(new HtmlString("<span x-text='desc'>Total Neto Cuantificable</span>")),
        ];
    }
}
