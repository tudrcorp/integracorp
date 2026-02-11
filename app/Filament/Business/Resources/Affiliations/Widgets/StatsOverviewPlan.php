<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

class StatsOverviewPlan extends StatsOverviewWidget
{
    use InteractsWithPageTable;

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

        /**
         * Lógica de obtención de datos:
         * Obtenemos el conteo total por plan y el conteo del mes actual.
         */
        $counts = [
            1 => [
                'total' => $this->getPageTableQuery()->where('plan_id', 1)->count(),
                'mes' => $this->getPageTableQuery()->where('plan_id', 1)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)->count(),
            ],
            2 => [
                'total' => $this->getPageTableQuery()->where('plan_id', 2)->count(),
                'mes' => $this->getPageTableQuery()->where('plan_id', 2)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)->count(),
            ],
            3 => [
                'total' => $this->getPageTableQuery()->where('plan_id', 3)->count(),
                'mes' => $this->getPageTableQuery()->where('plan_id', 3)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)->count(),
            ],
        ];

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

        return [
            Stat::make('PLAN INICIAL', $counts[1]['total'] . ' Afiliados')
                ->description('Plan básico')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $iosStyles . ' border-[#9ce1ff]',
                    'x-data' => "{ label: '{$counts[1]['total']} Afiliados', desc: 'Plan básico' }",
                    '@mouseenter' => "label = '{$counts[1]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[1]['total']} Afiliados'; desc = 'Plan básico'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[1]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Plan básico</span>")),

            Stat::make('PLAN IDEAL', $counts[2]['total'] . ' Afiliados')
                ->description('Asistencia Médica')
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $iosStyles . ' border-[#25b4e7]',
                    'x-data' => "{ label: '{$counts[2]['total']} Afiliados', desc: 'Asistencia Médica' }",
                    '@mouseenter' => "label = '{$counts[2]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[2]['total']} Afiliados'; desc = 'Asistencia Médica'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[2]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Asistencia Médica</span>")),

            Stat::make('PLAN ESPECIAL', $counts[3]['total'] . ' Afiliados')
                ->description('Emergencias Médicas')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => $iosStyles . ' border-[#2d89ca]',
                    'x-data' => "{ label: '{$counts[3]['total']} Afiliados', desc: 'Emergencias Médicas' }",
                    '@mouseenter' => "label = '{$counts[3]['mes']} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$counts[3]['total']} Afiliados'; desc = 'Emergencias Médicas'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$counts[3]['total']} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Emergencias Médicas</span>")),
        ];
    }
}
