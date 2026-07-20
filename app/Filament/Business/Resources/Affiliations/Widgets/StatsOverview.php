<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    private const CARD_TRANSITION = 'transition-[transform,box-shadow,border-color] duration-300';

    protected function getTablePage(): string
    {
        return ListAffiliations::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        $stats = $this->getPageTableQuery()
            ->reorder()
            ->where('status', 'ACTIVA')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as month_count', [
                $now->month,
                $now->year,
            ])
            ->first();

        $totalAfiliados = (int) ($stats->total_count ?? 0);
        $totalAfiliadosMes = (int) ($stats->month_count ?? 0);

        $cardAfiliados = 'cursor-default overflow-hidden '.self::CARD_TRANSITION.' rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';

        return [
            Stat::make('Total Afiliados Individuales', $totalAfiliados.' Afiliados')
                ->icon('heroicon-m-user-group')
                ->description('Con Planes Individuales')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardAfiliados,
                    'style' => 'min-height: 130px;',
                    'x-data' => "{ label: '{$totalAfiliados} Afiliados', desc: 'Con Planes Individuales' }",
                    '@mouseenter' => "label = '{$totalAfiliadosMes} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$totalAfiliados} Afiliados'; desc = 'Con Planes Individuales'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$totalAfiliados} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>Con Planes Individuales</span>")),
        ];
    }
}
