<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Support\Operations\SupplierListStatCounts;
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
        $base = $this->getPageTableQuery();

        $now = Carbon::now();
        $counts = SupplierListStatCounts::summary($base, $now->copy()->startOfMonth(), $now->copy()->endOfMonth());

        $valor = number_format($counts['total'], 0, ',', '.');
        $valorMes = number_format($counts['registered_in_month'], 0, ',', '.');
        $valorConCarta = number_format($counts['with_acceptance_letter'], 0, ',', '.');
        $valorNoConvenidos = number_format($counts['not_agreed'], 0, ',', '.');
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
                        <span class='text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400'>
                            MES ACTUAL · {$monthLabel}
                        </span>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500',
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('CON CARTA DE ACEPTACIÓN', $valorConCarta)
                ->columnSpan([
                    'default' => 1,
                    'sm' => 1,
                ])
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-semibold uppercase tracking-wide text-success-600 dark:text-success-400'>
                            GENERALES + PREFERENCIALES
                        </span>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('PROVEEDORES NO CONVENIDOS', $valorNoConvenidos)
                ->columnSpan([
                    'default' => 1,
                    'sm' => 1,
                ])
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-semibold uppercase tracking-wide text-warning-600 dark:text-warning-400'>
                            REGISTRADOS DESDE OPERACIONES
                        </span>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500',
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
