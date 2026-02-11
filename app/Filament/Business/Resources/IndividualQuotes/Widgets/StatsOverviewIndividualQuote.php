<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Models\IndividualQuote;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;

use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewIndividualQuote extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE REGISTROS DE COTIZACIÓN INDIVIDUAL EMITIDAS';

    protected ?string $description = 'Distribución de ingresos mensuales según el tipo de suscripción. Pasa el mouse para ver datos del mes actual.';

    protected function getTablePage(): string
    {
        return ListIndividualQuotes::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        /**
         * Lógica de obtención de datos:
         * Obtenemos el conteo total histórico y el conteo del mes actual para cada plan.
         */
        $plans = [
            '1' => ['label' => 'COTIZACIONES PLAN INICIAL', 'color' => 'planIncial', 'border' => '#9ce1ff', 'icon' => 'heroicon-m-check-badge'],
            '2' => ['label' => 'COTIZACIONES PLAN IDEAL', 'color' => 'planIdeal', 'border' => '#25b4e7', 'icon' => 'heroicon-m-star'],
            '3' => ['label' => 'COTIZACIONES PLAN ESPECIAL', 'color' => 'planEspecial', 'border' => '#2d89ca', 'icon' => 'heroicon-m-sparkles'],
            'CM' => ['label' => 'COTIZACIONES MULTIPLAN', 'color' => 'planCorp', 'border' => '#3b82f6', 'icon' => 'heroicon-m-building-office'],
        ];

        $stats = [];

        /**
         * Configuración de estilos iOS Premium
         */
        $iosStyles = '
            group cursor-pointer transition-all duration-500 ease-in-out 
            rounded-xl border-b-4 antialiased
            hover:border-[#10b981] dark:hover:border-[#34c759]
            hover:shadow-[inset_0_-50px_40px_-20px_rgba(16,185,129,0.15)] 
            dark:hover:shadow-[inset_0_-50px_40px_-20px_rgba(52,199,89,0.25)] 
            hover:scale-[1.01] 
            [&_*]:transition-all [&_*]:duration-500 
            group-hover:[&_.fi-wi-stats-overview-stat-value]:scale-110 
            group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#059669]
            dark:group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#34c759]
            group-hover:[&_.fi-wi-stats-overview-stat-label]:blur-[1.5px] 
            group-hover:[&_.fi-wi-stats-overview-stat-label]:opacity-60 
            group-hover:[&_svg]:blur-[1.5px] 
            group-hover:[&_svg]:opacity-40 
            group-hover:[&_.fi-wi-stats-overview-stat-description]:blur-[1.5px] 
            group-hover:[&_.fi-wi-stats-overview-stat-description]:opacity-60
        ';

        foreach ($plans as $key => $config) {
            $total = IndividualQuote::where('plan', $key)->count();
            $mes = IndividualQuote::where('plan', $key)
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count();

            $stats[] = Stat::make($config['label'], $total)
                ->description('Histórico Total')
                ->descriptionIcon($config['icon'])
                ->color($config['color'])
                ->extraAttributes([
                    'class' => $iosStyles . " border-[{$config['border']}]",
                    'x-data' => "{ valor: '{$total}', desc: 'Histórico Total' }",
                    '@mouseenter' => "valor = '{$mes}'; desc = 'Emitidas en {$mesActualNombre}'",
                    '@mouseleave' => "valor = '{$total}'; desc = 'Histórico Total'",
                ])
                ->value(new HtmlString("<span x-text='valor'>{$total}</span>"))
                ->description(new HtmlString("<span x-text='desc'>Histórico Total</span>"));
        }

        return $stats;
    }
}
