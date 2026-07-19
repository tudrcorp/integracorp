<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\Affiliate;
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
        return ListAffiliations::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        $rows = Affiliate::query()
            ->where('status', 'ACTIVO')
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

        $counts = [];
        foreach ([1, 2, 3] as $planId) {
            $counts[$planId] = [
                'total' => (int) ($rows->get($planId)->total_count ?? 0),
                'mes' => (int) ($rows->get($planId)->month_count ?? 0),
            ];
        }

        $cardPlan1 = 'cursor-default overflow-hidden '.self::CARD_TRANSITION.' rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardPlan2 = 'cursor-default overflow-hidden '.self::CARD_TRANSITION.' rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';
        $cardPlan3 = 'cursor-default overflow-hidden '.self::CARD_TRANSITION.' rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500';

        return [
            Stat::make('PLAN INICIAL', $counts[1]['total'].' Afiliados')
                ->description('Plan básico')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardPlan1,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$counts[1]['total']} Afiliados', desc: 'Plan básico' }",
                    '@mouseenter' => "label = '{$counts[1]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[1]['total']} Afiliados'; desc = 'Plan básico'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[1]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Plan básico</span>")),

            Stat::make('PLAN IDEAL', $counts[2]['total'].' Afiliados')
                ->description('Asistencia Médica')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $cardPlan2,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$counts[2]['total']} Afiliados', desc: 'Asistencia Médica' }",
                    '@mouseenter' => "label = '{$counts[2]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[2]['total']} Afiliados'; desc = 'Asistencia Médica'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[2]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Asistencia Médica</span>")),

            Stat::make('PLAN ESPECIAL', $counts[3]['total'].' Afiliados')
                ->description('Emergencias Médicas')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => $cardPlan3,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$counts[3]['total']} Afiliados', desc: 'Emergencias Médicas' }",
                    '@mouseenter' => "label = '{$counts[3]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[3]['total']} Afiliados'; desc = 'Emergencias Médicas'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[3]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Emergencias Médicas</span>")),
        ];
    }
}
