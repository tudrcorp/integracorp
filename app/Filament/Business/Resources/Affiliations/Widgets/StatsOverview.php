<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliate;
use App\Models\Affiliation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use Carbon\Carbon;
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

        /**
         * Configuración de estilos iOS Premium:
         * Incluye resplandor dinámico, borde adaptativo y efecto de desenfoque (blur).
         */
        $iosStyles = '
            group cursor-pointer transition-all duration-500 ease-in-out 
            rounded-xl border-b-4 antialiased
            
            /* Animación de Borde y Resplandor Verde */
            hover:border-[#10b981] dark:hover:border-[#34c759]
            hover:shadow-[inset_0_-50px_40px_-20px_rgba(16,185,129,0.15)] 
            dark:hover:shadow-[inset_0_-50px_40px_-20px_rgba(52,199,89,0.25)] 
            hover:scale-[1.01] 
            
            /* Sincronización de hijos */
            [&_*]:transition-all [&_*]:duration-500 
            
            /* Resaltar Valor Principal */
            group-hover:[&_.fi-wi-stats-overview-stat-value]:scale-110 
            group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#059669]
            dark:group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#34c759]
            
            /* Desenfoque de elementos secundarios */
            group-hover:[&_.fi-wi-stats-overview-stat-label]:blur-[1.5px] 
            group-hover:[&_.fi-wi-stats-overview-stat-label]:opacity-60 
            group-hover:[&_svg]:blur-[1.5px] 
            group-hover:[&_svg]:opacity-40 
            group-hover:[&_.fi-wi-stats-overview-stat-description]:blur-[1.5px] 
            group-hover:[&_.fi-wi-stats-overview-stat-description]:opacity-60
        ';

        return [
            Stat::make('Total Afiliados Individuales', $totalAfiliados . ' Afiliados')
                ->icon('heroicon-m-user-group')
                ->description('Con Planes Individuales')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $iosStyles,
                    'x-data' => "{ label: '{$totalAfiliados} Afiliados', desc: 'Con Planes Individuales' }",
                    '@mouseenter' => "label = '{$totalAfiliadosMes} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$totalAfiliados} Afiliados'; desc = 'Con Planes Individuales'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$totalAfiliados} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Con Planes Individuales</span>")),

            Stat::make('Total Neto', 'US$ ' . number_format($totalNeto, 2, ',', '.'))
                ->icon('heroicon-m-currency-dollar')
                ->description('Total Neto Cuantificable')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $iosStyles,
                    'x-data' => "{ 
                        valor: 'US$ " . number_format($totalNeto, 2, ',', '.') . "', 
                        desc: 'Total Neto Cuantificable' 
                    }",
                    '@mouseenter' => "
                        valor = 'US$ " . number_format($totalNetoMes, 2, ',', '.') . "'; 
                        desc = 'Recaudado en " . $mesActualNombre . "';
                    ",
                    '@mouseleave' => "
                        valor = 'US$ " . number_format($totalNeto, 2, ',', '.') . "'; 
                        desc = 'Total Neto Cuantificable';
                    ",
                ])
                ->value(new HtmlString("<span x-text='valor'>US$ " . number_format($totalNeto, 2, ',', '.') . "</span>"))
                ->description(new HtmlString("<span x-text='desc'>Total Neto Cuantificable</span>")),
        ];
    }
}
