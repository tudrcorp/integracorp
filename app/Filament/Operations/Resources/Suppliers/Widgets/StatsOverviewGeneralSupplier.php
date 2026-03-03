<?php

namespace App\Filament\Operations\Resources\Suppliers\Widgets;

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewGeneralSupplier extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'PROVEEDORES GENERALES';

    protected ?string $description = 'Resumen de proveedores con convenio general.';

    protected function getTablePage(): string
    {
        return ListSuppliers::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $nombreMes = ucfirst($now->translatedFormat('F'));

        $baseQuery = $this->getPageTableQuery()
            ->where('status_convenio', 'like', '%GENERAL%');

        $totalHistorico = (clone $baseQuery)->count();
        $totalMesActual = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $totalAfiliado = (clone $baseQuery)->where('status_sistema', 'AFILIADO')->count();
        $totalAfiliadoMesActual = (clone $baseQuery)
            ->where('status_sistema', 'AFILIADO')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();
        $totalEnProceso = (clone $baseQuery)->where('status_sistema', 'EN PROCESO')->count();
        $totalEnProcesoMesActual = (clone $baseQuery)
            ->where('status_sistema', 'EN PROCESO')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $totalMesActualLabel = $totalMesActual === 0 ? 'sin nuevos registros' : (string) $totalMesActual;
        $totalAfiliadoMesActual = $totalAfiliadoMesActual === 0 ? 'sin nuevos registros' : (string) $totalAfiliadoMesActual;
        $totalEnProcesoMesActual = $totalEnProcesoMesActual === 0 ? 'sin nuevos registros' : (string) $totalEnProcesoMesActual;

        $cardBase = 'cursor-default rounded-2xl min-h-[120px] transition-all duration-300 ease-out shadow-sm border border-gray-200/60 dark:border-gray-700/50 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.99]';

        return [
            Stat::make('TOTAL GENERAL', $totalHistorico)
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-medium text-gray-500 dark:text-gray-400'>
                            TOTAL HISTÓRICO
                        </span>
                        <div class='flex items-center gap-2.5 mt-1'>
                            <span class='px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'>
                                Total ({$nombreMes}):
                            </span>
                            <span class='text-sm font-bold text-gray-900 dark:text-white'>
                                {$totalMesActualLabel}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->extraAttributes([
                    'class' => "{$cardBase} hover:ring-2 hover:ring-blue-400/60 hover:border-blue-300/50 dark:hover:border-blue-600/50",
                ]),

            Stat::make('AFILIADO', $totalAfiliado)
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-medium text-gray-500 dark:text-gray-400'>
                            TOTAL HISTÓRICO
                        </span>
                        <div class='flex items-center gap-2.5 mt-1'>
                            <span class='px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'>
                                Total ({$nombreMes}):
                            </span>
                            <span class='text-sm font-bold text-gray-900 dark:text-white'>
                                {$totalAfiliadoMesActual}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->extraAttributes([
                    'class' => "{$cardBase} hover:ring-2 hover:ring-emerald-400/60 hover:border-emerald-300/50 dark:hover:border-emerald-600/50",
                ]),

            Stat::make('EN PROCESO', $totalEnProceso)
                ->description(new HtmlString("
                    <div class='flex flex-col mt-1'>
                        <span class='text-xs font-medium text-gray-500 dark:text-gray-400'>
                            TOTAL HISTÓRICO
                        </span>
                        <div class='flex items-center gap-2.5 mt-1'>
                            <span class='px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'>
                                Total ({$nombreMes}):
                            </span>
                            <span class='text-sm font-bold text-gray-900 dark:text-white'>
                                {$totalEnProcesoMesActual}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->extraAttributes([
                    'class' => "{$cardBase} hover:ring-2 hover:ring-amber-400/60 hover:border-amber-300/50 dark:hover:border-amber-600/50",
                ]),
        ];
    }
}
