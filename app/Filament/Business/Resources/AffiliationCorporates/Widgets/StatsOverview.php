<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use Carbon\Carbon;
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
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        // --- CÁLCULOS PARA TOTAL CORPORATIVOS ---
        // Valor por defecto (Filtro de tabla)
        $totalEmpresas = $this->getPageTableQuery()->where('status', 'ACTIVA')->count();
        // Valor del mes en curso
        $totalEmpresasMes = $this->getPageTableQuery()
            ->where('status', 'ACTIVA')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        // --- CÁLCULOS PARA TOTAL NETO ---
        // Valor por defecto
        $totalNeto = $this->getPageTableQuery()->where('status', 'ACTIVA')->sum('total_amount');
        // Valor del mes en curso
        $totalNetoMes = $this->getPageTableQuery()
            ->where('status', 'ACTIVA')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('total_amount');

        /**
         * Estilos CSS personalizados para el efecto de enfoque iOS y transiciones.
         */
        $iosFocusBlurStyles = '
            group cursor-pointer transition-all duration-500 ease-in-out 
            rounded-xl border-b-4 border-[#25b4e7] 
            antialiased 
            hover:border-[#10b981] dark:hover:border-[#34c759]
            hover:shadow-[inset_0_-50px_40px_-20px_rgba(16,185,129,0.15)] 
            dark:hover:shadow-[inset_0_-50px_40px_-20px_rgba(52,199,89,0.25)] 
            hover:scale-[1.01] 
            [&_*]:transition-all [&_*]:duration-500 
        ';

        return [
            Stat::make('Total Corporativos', $totalEmpresas . ' empresas')
                ->icon('heroicon-m-user-group')
                ->description('Total histórico / Acumulado')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                    // Lógica Alpine.js para cambiar el texto en hover
                    'x-data' => "{ label: '{$totalEmpresas} empresas', desc: 'Total histórico / Acumulado' }",
                    '@mouseenter' => "label = '{$totalEmpresasMes} empresas'; desc = 'Solo en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$totalEmpresas} empresas'; desc = 'Total histórico / Acumulado'",
                ])
                // Inyectamos el valor dinámico mediante JS en la vista de Filament
                ->value(new \Illuminate\Support\HtmlString("<span x-text='label'>{$totalEmpresas} empresas</span>"))
                ->description(new \Illuminate\Support\HtmlString("<span x-text='desc'>Total histórico / Acumulado</span>")),

            Stat::make('Total Neto', 'US$ ' . number_format($totalNeto, 2, ',', '.'))
                ->icon('heroicon-m-currency-dollar')
                ->description('Total en US$ Cuantificable')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                    'x-data' => "{ 
                        valor: 'US$ " . number_format($totalNeto, 2, ',', '.') . "', 
                        desc: 'Total en US$ Cuantificable' 
                    }",
                    '@mouseenter' => "
                        valor = 'US$ " . number_format($totalNetoMes, 2, ',', '.') . "'; 
                        desc = 'Recaudado en " . $mesActualNombre . "';
                    ",
                    '@mouseleave' => "
                        valor = 'US$ " . number_format($totalNeto, 2, ',', '.') . "'; 
                        desc = 'Total en US$ Cuantificable';
                    ",
                ])
                ->value(new \Illuminate\Support\HtmlString("<span x-text='valor'>US$ " . number_format($totalNeto, 2, ',', '.') . "</span>"))
                ->description(new \Illuminate\Support\HtmlString("<span x-text='desc'>Total en US$ Cuantificable</span>")),
        ];
    }

    
}