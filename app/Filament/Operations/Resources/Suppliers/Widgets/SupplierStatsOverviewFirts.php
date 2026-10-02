<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\Supplier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class SupplierStatsOverviewFirts extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static ?int $sort = 0;

    protected ?string $heading = 'TOTAL DE PROVEEDORES';

    protected ?string $description = 'Total general de proveedores. Respeta los filtros activos en el listado.';

    protected function getTablePage(): string
    {
        return ListSuppliers::class;
    }

    /**
     * @return array<string, int|string|null>|int|null
     */
    protected function getColumns(): array|int|null
    {
        return [
            'default' => 1,
            'sm' => 3,
            'md' => 5,
            'xl' => 5,
        ];
    }

    protected function getStats(): array
    {
        $table = (new Supplier)->getTable();
        $base = $this->getPageTableQuery();
        $total = (clone $base)->count();
        $valor = number_format($total, 0, ',', '.');

        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $monthTotal = (clone $base)
            ->whereBetween("{$table}.created_at", [$monthStart, $monthEnd])
            ->count();
        $valorMes = number_format($monthTotal, 0, ',', '.');
        $monthLabel = ucfirst($now->locale(app()->getLocale())->translatedFormat('F Y'));

        return [
            Stat::make('PROVEEDORES REGISTRADOS', $valor)
                ->columnSpan([
                    'default' => 1,
                    'sm' => 1,
                ])
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-semibold uppercase tracking-wide text-info-600 dark:text-info-400'>
                            TOTAL GENERAL
                        </span>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info')
                ->extraAttributes([
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500',
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('REGISTRADOS EN EL MES', $valorMes)
                ->columnSpan([
                    'default' => 1,
                    'sm' => 1,
                ])
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-semibold uppercase tracking-wide text-success-600 dark:text-success-400'>
                            MES ACTUAL · {$monthLabel}
                        </span>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
