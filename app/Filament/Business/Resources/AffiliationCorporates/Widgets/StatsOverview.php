<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
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
        return ListAffiliationCorporates::class;
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

        $totalEmpresas = (int) ($stats->total_count ?? 0);
        $totalEmpresasMes = (int) ($stats->month_count ?? 0);

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
            Stat::make('Total Corporativos', $totalEmpresas.' empresas')
                ->icon('heroicon-m-user-group')
                ->description('Total histórico / Acumulado')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $iosFocusBlurStyles,
                    'x-data' => "{ label: '{$totalEmpresas} empresas', desc: 'Total histórico / Acumulado' }",
                    '@mouseenter' => "label = '{$totalEmpresasMes} empresas'; desc = 'Solo en {$mesActualNombre}'",
                    '@mouseleave' => "label = '{$totalEmpresas} empresas'; desc = 'Total histórico / Acumulado'",
                ])
                ->value(new HtmlString("<span x-text='label'>{$totalEmpresas} empresas</span>"))
                ->description(new HtmlString("<span x-text='desc'>Total histórico / Acumulado</span>")),
        ];
    }
}
