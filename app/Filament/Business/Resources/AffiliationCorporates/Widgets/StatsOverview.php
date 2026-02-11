<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;


class StatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliationCorporates::class;
    }
    protected function getStats(): array
    {
        /**
         * Configuración de colores dinámica:
         * Modo Claro: Verde Esmeralda Vibrante (#10b981)
         * Modo Oscuro: Verde iOS Clásico (#34c759)
         */

        $iosFocusBlurStyles = '
            group cursor-pointer transition-all duration-500 ease-in-out 
            rounded-xl border-b-4 border-[#25b4e7] 
            antialiased 
            
            /* Cambios de Borde según el tema */
            hover:border-[#10b981] dark:hover:border-[#34c759]
            
            /* Resplandor interno dinámico (Más intenso en light, sutil en dark) */
            hover:shadow-[inset_0_-50px_40px_-20px_rgba(16,185,129,0.15)] 
            dark:hover:shadow-[inset_0_-50px_40px_-20px_rgba(52,199,89,0.25)] 
            
            hover:scale-[1.01] 
            
            /* Transiciones para todos los elementos internos */
            [&_*]:transition-all [&_*]:duration-500 
            
            /* Valor Principal: Resaltado dinámico */
            group-hover:[&_.fi-wi-stats-overview-stat-value]:scale-110 
            group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#059669]
            dark:group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#34c759]
            
            /* Elementos secundarios: Desenfoque proporcional */
            group-hover:[&_.fi-wi-stats-overview-stat-label]:blur-[1.5px] 
            group-hover:[&_.fi-wi-stats-overview-stat-label]:opacity-60 
            
            group-hover:[&_svg]:blur-[1.5px] 
            group-hover:[&_svg]:opacity-40 
            
            group-hover:[&_.fi-wi-stats-overview-stat-description]:blur-[1.5px] 
            group-hover:[&_.fi-wi-stats-overview-stat-description]:opacity-60
        ';

        return [
            Stat::make('Total Corporativos', $this->getPageTableQuery()->where('status', 'ACTIVA')->count() . ' empresas')
                ->icon('heroicon-m-user-group')
                ->description('Empresas y/o Grupos Corporativos')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                ]),

            Stat::make('Total Neto', 'US$ ' . number_format($this->getPageTableQuery()->where('status', 'ACTIVA')->sum('total_amount'), 2, ',', '.'))
                ->icon('heroicon-m-currency-dollar')
                ->description('Total en US$ Cuantificable')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                ]),
        ];
    }

    
}