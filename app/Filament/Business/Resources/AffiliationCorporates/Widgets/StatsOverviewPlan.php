<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;


use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Models\AffiliationCorporatePlan;
use App\Models\AfilliationCorporatePlan;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewPlan extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE AFILIACIONES POR PLAN';

    protected ?string $description = 'Distribución de afiliaciones mensuales según el tipo de suscripción.';

    protected function getTablePage(): string
    {
        return ListAffiliationCorporates::class;
    }
    protected function getStats(): array
    {
        /**
         * Optimización de Consulta:
         * Obtenemos los conteos de todos los planes en una sola consulta agrupada.
         */
        $planStats = AfilliationCorporatePlan::select('plan_id', DB::raw('count(*) as total'))
            ->where('status', 'ACTIVA')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        /**
         * Configuración de estilos iOS Premium:
         * Incluye resplandor dinámico, borde adaptativo y efecto de desenfoque (blur)
         * sobre los elementos secundarios al hacer hover.
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
            Stat::make('PLAN INICIAL', ($planStats[1] ?? 0) . ' Afiliados')
                ->description('Plan básico')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $iosStyles . ' border-[#9ce1ff]',
                ]),

            Stat::make('PLAN IDEAL', ($planStats[2] ?? 0) . ' Afiliados')
                ->description('Con Asistencia Médica por Accidentes')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $iosStyles . ' border-[#25b4e7]',
                ]),

            Stat::make('PLAN ESPECIAL', ($planStats[3] ?? 0) . ' Afiliados')
                ->description('Con Emergencias Médicas por Patologías Listadas')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => $iosStyles . ' border-[#2d89ca]',
                ]),
        ];
    }
}
