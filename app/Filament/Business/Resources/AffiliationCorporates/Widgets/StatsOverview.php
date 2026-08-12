<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Models\AffiliationCorporate;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class StatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    private const CARD_TRANSITION = 'transition-[transform,box-shadow,border-color] duration-300';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');

        $stats = $this->affiliationCorporatesQuery()
            ->where('status', 'ACTIVA')
            ->toBase()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as month_count', [
                $now->month,
                $now->year,
            ])
            ->selectRaw('COUNT(DISTINCT code_agency) as agencies_count')
            ->selectRaw('COUNT(DISTINCT agent_id) as agents_count')
            ->first();

        $totalGrupos = (int) ($stats->total_count ?? 0);
        $totalGruposMes = (int) ($stats->month_count ?? 0);
        $totalAgencias = (int) ($stats->agencies_count ?? 0);
        $totalAgentes = (int) ($stats->agents_count ?? 0);

        $iosFocusBlurStyles = '
            group cursor-pointer '.self::CARD_TRANSITION.' ease-in-out
            rounded-xl border-b-4 border-[#25b4e7]
            antialiased
            hover:border-[#10b981] dark:hover:border-[#34c759]
            hover:shadow-[inset_0_-50px_40px_-20px_rgba(16,185,129,0.15)]
            dark:hover:shadow-[inset_0_-50px_40px_-20px_rgba(52,199,89,0.25)]
            hover:scale-[1.01]
        ';

        return [
            Stat::make('Grupos Activos', $totalGrupos.' grupos')
                ->icon('heroicon-m-building-office-2')
                ->description('Afiliaciones corporativas con estatus ACTIVA')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                    'x-data' => "{ label: '{$totalGrupos} grupos', desc: 'Afiliaciones corporativas con estatus ACTIVA' }",
                    '@mouseenter' => "label = '{$totalGruposMes} grupos'; desc = 'Activadas en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$totalGrupos} grupos'; desc = 'Afiliaciones corporativas con estatus ACTIVA'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$totalGrupos} grupos</span>"))
                ->description(new HtmlString("<span x-text='desc'>Afiliaciones corporativas con estatus ACTIVA</span>")),

            Stat::make('Agencias', $totalAgencias.' agencias')
                ->icon('heroicon-m-building-storefront')
                ->description('Agencias vinculadas a grupos activos')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                ])
                ->value(new HtmlString("{$totalAgencias} agencias"))
                ->description(new HtmlString('Agencias vinculadas a grupos activos')),

            Stat::make('Agentes', $totalAgentes.' agentes')
                ->icon('heroicon-m-user')
                ->description('Agentes vinculados a grupos activos')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                ])
                ->value(new HtmlString("{$totalAgentes} agentes"))
                ->description(new HtmlString('Agentes vinculados a grupos activos')),
        ];
    }

    /**
     * Misma base de visibilidad que la tabla del listado (account managers).
     */
    protected function affiliationCorporatesQuery(): Builder
    {
        $query = AffiliationCorporate::query();

        if (Auth::user()?->is_accountManagers) {
            $query->where('ownerAccountManagers', Auth::id());
        }

        return $query;
    }
}
