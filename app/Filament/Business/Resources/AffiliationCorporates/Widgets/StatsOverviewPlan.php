<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Models\AffiliateCorporate;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class StatsOverviewPlan extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    private const CARD_TRANSITION = 'transition-[transform,box-shadow,border-color] duration-300';

    /**
     * @var array<int, array{label: string, description: string, icon: string, color: string, border: string}>
     */
    private const PLAN_CARDS = [
        1 => [
            'label' => 'PLAN INICIAL',
            'description' => 'Afiliados activos · Plan básico',
            'icon' => 'heroicon-m-check-badge',
            'color' => 'planIncial',
            'border' => 'border-[#9ce1ff]',
        ],
        2 => [
            'label' => 'PLAN IDEAL',
            'description' => 'Afiliados activos · Asistencia Médica',
            'icon' => 'heroicon-m-star',
            'color' => 'planIdeal',
            'border' => 'border-[#25b4e7]',
        ],
        3 => [
            'label' => 'PLAN ESPECIAL',
            'description' => 'Afiliados activos · Emergencias Médicas',
            'icon' => 'heroicon-m-sparkles',
            'color' => 'planEspecial',
            'border' => 'border-[#2d89ca]',
        ],
        16 => [
            'label' => 'PLAN ESCOLAR AP 1K',
            'description' => 'Afiliados activos · Escolar AP 1K',
            'icon' => 'heroicon-m-academic-cap',
            'color' => 'info',
            'border' => 'border-[#38bdf8]',
        ],
        17 => [
            'label' => 'PLAN ESCOLAR AP 3K',
            'description' => 'Afiliados activos · Escolar AP 3K',
            'icon' => 'heroicon-m-academic-cap',
            'color' => 'warning',
            'border' => 'border-[#f59e0b]',
        ],
    ];

    protected ?string $heading = 'AFILIADOS ACTIVOS POR PLAN';

    protected ?string $description = 'Afiliados con estatus ACTIVO en grupos corporativos activos. Pasa el mouse para ver datos del mes actual.';

    protected function getStats(): array
    {
        $now = Carbon::now();
        $mesActualNombre = $now->translatedFormat('F');
        $planIds = array_keys(self::PLAN_CARDS);

        $rows = AffiliateCorporate::query()
            ->where('status', 'ACTIVO')
            ->whereHas('affiliationCorporate', function ($query): void {
                $query->where('status', 'ACTIVA');

                if (Auth::user()?->is_accountManagers) {
                    $query->where('ownerAccountManagers', Auth::id());
                }
            })
            ->whereIn('plan_id', $planIds)
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
        foreach ($planIds as $planId) {
            $planStatsTotal[$planId] = (int) ($rows->get($planId)->total_count ?? 0);
            $planStatsMes[$planId] = (int) ($rows->get($planId)->month_count ?? 0);
        }

        $totalActivos = array_sum($planStatsTotal);
        $totalActivosMes = array_sum($planStatsMes);

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

        $getAlpineData = function (int $total, int $mes, string $descDefault) use ($mesActualNombre): array {
            return [
                'x-data' => "{ label: '{$total} Afiliados', desc: '{$descDefault}' }",
                '@mouseenter' => "label = '{$mes} Afiliados'; desc = 'Nuevos en {$mesActualNombre}'",
                '@mouseleave' => "label = '{$total} Afiliados'; desc = '{$descDefault}'",
            ];
        };

        $stats = [];

        foreach (self::PLAN_CARDS as $planId => $card) {
            $total = $planStatsTotal[$planId] ?? 0;
            $mes = $planStatsMes[$planId] ?? 0;

            $stats[] = Stat::make($card['label'], $total.' Afiliados')
                ->description($card['description'])
                ->descriptionIcon($card['icon'])
                ->color($card['color'])
                ->extraAttributes(array_merge(
                    ['class' => $iosStyles.' '.$card['border']],
                    $getAlpineData($total, $mes, $card['description'])
                ))
                ->value(new HtmlString("<span x-text='label'>{$total} Afiliados</span>"))
                ->description(new HtmlString("<span x-text='desc'>{$card['description']}</span>"));
        }

        $stats[] = Stat::make('TOTAL ACTIVOS', $totalActivos.' Afiliados')
            ->description('Suma de afiliados activos de todos los planes')
            ->descriptionIcon('heroicon-m-calculator')
            ->color('success')
            ->extraAttributes(array_merge(
                ['class' => $iosStyles.' border-[#10b981]'],
                $getAlpineData($totalActivos, $totalActivosMes, 'Suma de afiliados activos de todos los planes')
            ))
            ->value(new HtmlString("<span x-text='label'>{$totalActivos} Afiliados</span>"))
            ->description(new HtmlString("<span x-text='desc'>Suma de afiliados activos de todos los planes</span>"));

        return $stats;
    }
}
