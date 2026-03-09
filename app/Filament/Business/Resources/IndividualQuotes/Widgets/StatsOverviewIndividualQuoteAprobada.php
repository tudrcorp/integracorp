<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Models\IndividualQuote;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class StatsOverviewIndividualQuoteAprobada extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE REGISTROS DE COTIZACIÓN INDIVIDUAL EJECUTADAS';

    protected ?string $description = 'Distribución de cotizaciones según el tipo de suscripción. Pasa el mouse para ver datos del mes actual.';

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
         * Obtenemos el histórico de ejecutadas y el conteo del mes actual para cada plan.
         */
        $plans = [
            '1' => [
                'label' => 'COTIZACIONES PLAN INICIAL',
                'color' => 'planIncial',
                'icon' => 'heroicon-m-check-badge',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500',
            ],
            '2' => [
                'label' => 'COTIZACIONES PLAN IDEAL',
                'color' => 'planIdeal',
                'icon' => 'heroicon-m-star',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500',
            ],
            '3' => [
                'label' => 'COTIZACIONES PLAN ESPECIAL',
                'color' => 'planEspecial',
                'icon' => 'heroicon-m-sparkles',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500',
            ],
            'CM' => [
                'label' => 'COTIZACIONES MULTIPLAN',
                'color' => 'planCorp',
                'icon' => 'heroicon-m-building-office',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
            ],
        ];

        $stats = [];

        foreach ($plans as $key => $config) {
            // Histórico Total de Ejecutadas
            $total = IndividualQuote::where('plan', $key)
                ->where('status', 'EJECUTADA')
                ->count();

            // Ejecutadas en el Mes Actual
            $mes = IndividualQuote::where('plan', $key)
                ->where('status', 'EJECUTADA')
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count();

            $stats[] = Stat::make($config['label'], $total)
                ->description('Histórico Ejecutadas')
                ->descriptionIcon($config['icon'])
                ->color($config['color'])
                ->extraAttributes([
                    'class' => $config['cardClass'],
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ valor: '{$total}', desc: 'Histórico Ejecutadas' }",
                    '@mouseenter' => "valor = '{$mes}'; desc = 'Ejecutadas en {$mesActualNombre}'",
                    '@mouseleave' => "valor = '{$total}'; desc = 'Histórico Ejecutadas'",
                ])
                ->value(new HtmlString("<span x-text='valor'>{$total}</span>"))
                ->description(new HtmlString("<span x-text='desc'>Histórico Ejecutadas</span>"));
        }

        return $stats;
    }
}
