<?php

namespace App\Filament\Operations\Widgets;

use App\Models\Affiliation;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewPlan extends StatsOverviewWidget
{
    protected static ?int $sort = 11;

    protected ?string $pollingInterval = '10s';

    protected ?string $heading = 'ANALISIS DE AFILIACIONES POR PLAN';

    protected ?string $description = 'Distribucion de afiliaciones mensuales segun el tipo de suscripcion. Pasa el mouse para ver datos del mes actual.';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        $counts = [
            1 => [
                'total' => Affiliation::query()->where('plan_id', 1)->count(),
                'mes' => Affiliation::query()->where('plan_id', 1)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)->count(),
            ],
            2 => [
                'total' => Affiliation::query()->where('plan_id', 2)->count(),
                'mes' => Affiliation::query()->where('plan_id', 2)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)->count(),
            ],
            3 => [
                'total' => Affiliation::query()->where('plan_id', 3)->count(),
                'mes' => Affiliation::query()->where('plan_id', 3)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)->count(),
            ],
        ];

        $cardPlan1 = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardPlan2 = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';
        $cardPlan3 = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500';

        return [
            Stat::make('PLAN INICIAL', $counts[1]['total'].' Afiliados')
                ->description('Plan basico')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardPlan1,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$counts[1]['total']} Afiliados', desc: 'Plan basico' }",
                    '@mouseenter' => "label = '{$counts[1]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[1]['total']} Afiliados'; desc = 'Plan basico'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[1]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Plan basico</span>")),

            Stat::make('PLAN IDEAL', $counts[2]['total'].' Afiliados')
                ->description('Asistencia Medica')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $cardPlan2,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$counts[2]['total']} Afiliados', desc: 'Asistencia Medica' }",
                    '@mouseenter' => "label = '{$counts[2]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[2]['total']} Afiliados'; desc = 'Asistencia Medica'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[2]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Asistencia Medica</span>")),

            Stat::make('PLAN ESPECIAL', $counts[3]['total'].' Afiliados')
                ->description('Emergencias Medicas')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => $cardPlan3,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$counts[3]['total']} Afiliados', desc: 'Emergencias Medicas' }",
                    '@mouseenter' => "label = '{$counts[3]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[3]['total']} Afiliados'; desc = 'Emergencias Medicas'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[3]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Emergencias Medicas</span>")),
        ];
    }
}
