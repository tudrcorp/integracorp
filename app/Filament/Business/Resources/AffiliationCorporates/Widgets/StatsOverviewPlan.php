<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\AfilliationCorporatePlan;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewPlan extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    private const CARD_TRANSITION = 'transition-[transform,box-shadow,border-color] duration-300';

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

        $rows = AfilliationCorporatePlan::query()
            ->where('status', 'ACTIVA')
            ->whereIn('plan_id', [1, 2, 3])
            ->select('plan_id')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as month_count', [
                $now->month,
                $now->year,
            ])
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $planStatsTotal = [];
        $planStatsMes = [];
        foreach ([1, 2, 3] as $planId) {
            $planStatsTotal[$planId] = (int) ($rows->get($planId)->total_count ?? 0);
            $planStatsMes[$planId] = (int) ($rows->get($planId)->month_count ?? 0);
        }

        $iosStyles = '
            group cursor-pointer '.self::CARD_TRANSITION.' ease-in-out
            rounded-xl border-b-4 antialiased
            hover:border-[#10b981] dark:hover:border-[#34c759]
            hover:shadow-[inset_0_-50px_40px_-20px_rgba(16,185,129,0.15)]
            dark:hover:shadow-[inset_0_-50px_40px_-20px_rgba(52,199,89,0.25)]
            hover:scale-[1.01]
            group-hover:[&_.fi-wi-stats-overview-stat-value]:scale-110
            group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#059669]
            dark:group-hover:[&_.fi-wi-stats-overview-stat-value]:text-[#34c759]
        ';

        $getAlpineData = function ($total, $mes, $descDefault) use ($mesActualNombre) {
            return [
                'x-data' => "{ label: '{$total} Afiliados', desc: '{$descDefault}' }",
                '@mouseenter' => "label = '{$mes} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                '@mouseleave' => "label = '{$total} Afiliados'; desc = '{$descDefault}'",
            ];
        };

        return [
            Stat::make('PLAN INICIAL', ($planStatsTotal[1] ?? 0).' Afiliados')
                ->description('Plan básico')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes(array_merge(
                    ['class' => $iosStyles.' border-[#9ce1ff]'],
                    $getAlpineData($planStatsTotal[1] ?? 0, $planStatsMes[1] ?? 0, 'Plan básico')
                ))
                ->value(new HtmlString("<span x-text='label'>".($planStatsTotal[1] ?? 0).' Afiliados</span>'))
                ->description(new HtmlString("<span x-text='desc'>Plan básico</span>")),

            Stat::make('PLAN IDEAL', ($planStatsTotal[2] ?? 0).' Afiliados')
                ->description('Con Asistencia Médica')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes(array_merge(
                    ['class' => $iosStyles.' border-[#25b4e7]'],
                    $getAlpineData($planStatsTotal[2] ?? 0, $planStatsMes[2] ?? 0, 'Con Asistencia Médica')
                ))
                ->value(new HtmlString("<span x-text='label'>".($planStatsTotal[2] ?? 0).' Afiliados</span>'))
                ->description(new HtmlString("<span x-text='desc'>Con Asistencia Médica</span>")),

            Stat::make('PLAN ESPECIAL', ($planStatsTotal[3] ?? 0).' Afiliados')
                ->description('Con Emergencias Médicas')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes(array_merge(
                    ['class' => $iosStyles.' border-[#2d89ca]'],
                    $getAlpineData($planStatsTotal[3] ?? 0, $planStatsMes[3] ?? 0, 'Con Emergencias Médicas')
                ))
                ->value(new HtmlString("<span x-text='label'>".($planStatsTotal[3] ?? 0).' Afiliados</span>'))
                ->description(new HtmlString("<span x-text='desc'>Con Emergencias Médicas</span>")),
        ];
    }
}
