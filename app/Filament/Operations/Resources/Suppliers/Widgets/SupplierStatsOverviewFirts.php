<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
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

    protected function getStats(): array
    {
        $base = $this->getPageTableQuery();
        $total = (clone $base)->count();
        $valor = number_format($total, 0, ',', '.');

        return [
            Stat::make('PROVEEDORES REGISTRADOS', $valor)
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
        ];
    }
}
