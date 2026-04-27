<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\Supplier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class StatsOverviewGeneralSupplier extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static ?int $sort = 1;

    protected ?string $heading = 'PROVEEDORES GENERALES';

    protected ?string $description = 'Solo convenio GENERAL (valor exacto) y cada estatus de sistema. Respetan los filtros del listado.';

    protected function getTablePage(): string
    {
        return ListSuppliers::class;
    }

    protected function getColumns(): array
    {
        return [
            'lg' => '5',
        ];
    }

    protected function getStats(): array
    {
        $table = (new Supplier)->getTable();

        $base = $this->getPageTableQuery()
            ->where("{$table}.status_convenio", 'GENERAL');

        $estatus = [
            [
                'name' => 'CONVENIO GENERAL',
                'icon' => 'heroicon-m-building-library',
                'color' => 'primary',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500',
                'labelClass' => 'text-primary-600 dark:text-primary-400',
                'badgeClass' => 'bg-primary-100/90 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300',
                'criterion' => 'GENERAL',
                'apply' => static function (Builder $query) use ($table): Builder {
                    return $query->where("{$table}.status_convenio", 'GENERAL');
                },
            ],
            [
                'name' => 'PROVEEDORES GENERALES AFILIADOS',
                'icon' => 'heroicon-m-check-badge',
                'color' => 'primary',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500',
                'labelClass' => 'text-primary-600 dark:text-primary-400',
                'badgeClass' => 'bg-primary-100/90 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300',
                'criterion' => 'GENERAL · AFILIADO',
                'apply' => static function (Builder $query) use ($table): Builder {
                    return $query->where("{$table}.status_sistema", 'AFILIADO');
                },
            ],
            [
                'name' => 'ACTIVO AFILIADO',
                'icon' => 'heroicon-m-shield-check',
                'color' => 'success',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                'labelClass' => 'text-success-600 dark:text-success-400',
                'badgeClass' => 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300',
                'criterion' => 'GENERAL · ACTIVO AFILIADO',
                'apply' => static function (Builder $query) use ($table): Builder {
                    return $query->where("{$table}.status_sistema", 'ACTIVO AFILIADO');
                },
            ],
            [
                'name' => 'ACTIVO EN PROCESO',
                'icon' => 'heroicon-m-arrow-path',
                'color' => 'success',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                'labelClass' => 'text-success-600 dark:text-success-400',
                'badgeClass' => 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300',
                'criterion' => 'GENERAL · ACTIVO EN PROCESO',
                'apply' => static function (Builder $query) use ($table): Builder {
                    return $query->where("{$table}.status_sistema", 'ACTIVO EN PROCESO');
                },
            ],
            [
                'name' => 'EN PROCESO',
                'icon' => 'heroicon-m-clock',
                'color' => 'success',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                'labelClass' => 'text-success-600 dark:text-success-400',
                'badgeClass' => 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300',
                'criterion' => 'GENERAL · EN PROCESO',
                'apply' => static function (Builder $query) use ($table): Builder {
                    return $query->where("{$table}.status_sistema", 'EN PROCESO');
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
        }, $estatus);
    }
}
