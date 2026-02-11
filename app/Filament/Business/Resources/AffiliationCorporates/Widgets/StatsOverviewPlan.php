<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Models\AfilliationCorporatePlan;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class StatsOverviewPlan extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE AFILIACIONES POR PLAN';

    protected ?string $description = 'Distribución de afiliaciones mensuales según el tipo de suscripción. Pasa el mouse para ver datos del mes actual.';

    protected function getTablePage(): string
    {
        return ListAffiliationCorporates::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        /**
         * Conteos Totales (Históricos/Filtrados)
         */
        $planStatsTotal = AfilliationCorporatePlan::select('plan_id', DB::raw('count(*) as total'))
            ->where('status', 'ACTIVA')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        /**
         * Conteos del Mes en Curso
         */
        $planStatsMes = AfilliationCorporatePlan::select('plan_id', DB::raw('count(*) as total'))
            ->where('status', 'ACTIVA')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        /**
         * Estilos iOS Premium
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
        ';

        // Helper para construir la lógica de Alpine.js por cada Stat
        $getAlpineData = function ($total, $mes, $descDefault) use ($mesActualNombre) {
            return [
                'x-data' => "{ label: '{$total} Afiliados', desc: '{$descDefault}' }",
                '@mouseenter' => "label = '{$mes} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                '@mouseleave' => "label = '{$total} Afiliados'; desc = '{$descDefault}'",
            ];
        };

        return [
            Stat::make('PLAN INICIAL', ($planStatsTotal[1] ?? 0) . ' Afiliados')
                ->description('Plan básico')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes(array_merge(
                    ['class' => $iosStyles . ' border-[#9ce1ff]'],
                    $getAlpineData($planStatsTotal[1] ?? 0, $planStatsMes[1] ?? 0, 'Plan básico')
                ))
                ->value(new HtmlString("<span x-text='label'>" . ($planStatsTotal[1] ?? 0) . " Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Plan básico</span>")),

            Stat::make('PLAN IDEAL', ($planStatsTotal[2] ?? 0) . ' Afiliados')
                ->description('Con Asistencia Médica')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes(array_merge(
                    ['class' => $iosStyles . ' border-[#25b4e7]'],
                    $getAlpineData($planStatsTotal[2] ?? 0, $planStatsMes[2] ?? 0, 'Con Asistencia Médica')
                ))
                ->value(new HtmlString("<span x-text='label'>" . ($planStatsTotal[2] ?? 0) . " Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Con Asistencia Médica</span>")),

            Stat::make('PLAN ESPECIAL', ($planStatsTotal[3] ?? 0) . ' Afiliados')
                ->description('Con Emergencias Médicas')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes(array_merge(
                    ['class' => $iosStyles . ' border-[#2d89ca]'],
                    $getAlpineData($planStatsTotal[3] ?? 0, $planStatsMes[3] ?? 0, 'Con Emergencias Médicas')
                ))
                ->value(new HtmlString("<span x-text='label'>" . ($planStatsTotal[3] ?? 0) . " Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Con Emergencias Médicas</span>")),
        ];
    }
}
