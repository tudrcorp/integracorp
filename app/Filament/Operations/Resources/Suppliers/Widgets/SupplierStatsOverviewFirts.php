<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class SupplierStatsOverviewFirts extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static ?int $sort = 0;

    protected ?string $heading = 'PROVEEDORES POR TIPO DE CONVENIO';

    protected ?string $description = 'Totales de la tabla según criterio de convenio. Respetan los filtros activos en el listado.';

    protected function getTablePage(): string
    {
        return ListSuppliers::class;
    }

    protected function getStats(): array
    {
        $table = (new Supplier)->getTable();
        $base = $this->getPageTableQuery();

        $convenios = [
            [
                'name' => 'CONVENIO GENERAL',
                'icon' => 'heroicon-m-building-library',
                'color' => 'info',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500',
                'labelClass' => 'text-info-600 dark:text-info-400',
                'badgeClass' => 'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300',
                'criterion' => 'GENERAL',
                'apply' => static function (Builder $query) use ($table): Builder {
                    return $query->where("{$table}.status_convenio", 'GENERAL');
                },
            ],
            [
                'name' => 'CONVENIO PREFERENCIAL',
                'icon' => 'heroicon-m-star',
                'color' => 'success',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                'labelClass' => 'text-success-600 dark:text-success-400',
                'badgeClass' => 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300',
                'criterion' => 'PREFERENCIAL',
                'apply' => static function (Builder $query) use ($table): Builder {
                    return $query->where("{$table}.status_convenio", 'like', '%PREFERENCIAL%');
                },
            ],
        ];

        return array_map(function (array $row) use ($base): Stat {
            $total = ($row['apply'])(clone $base)->count();

            $valor = number_format($total, 0, ',', '.');

            return Stat::make($row['name'], $valor)
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-semibold uppercase tracking-wide {$row['labelClass']}'>
                            TOTAL PROVEEDORES
                        </span>
                        <div class='flex flex-wrap items-center gap-2.5 mt-1.5'>
                            <span class='px-2.5 py-1 text-xs font-bold rounded-lg {$row['badgeClass']} shadow-sm'>
                                Criterio:
                            </span>
                            <span class='text-sm font-bold text-gray-900 dark:text-white'>
                                {$row['criterion']}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon($row['icon'])
                ->color($row['color'])
                ->extraAttributes([
                    'class' => $row['cardClass'],
                    'style' => 'min-height: 130px;',
                ]);
        }, $convenios);
    }
}
