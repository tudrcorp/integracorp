<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Filament\Business\Resources\Agencies\Pages\ListAgencies;
use App\Models\Agency;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewAgency extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Panel de Control de Agencias';

    protected ?string $description = 'Métricas clave de la red operativa.';

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListAgencies::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $nombreMes = ucfirst($now->translatedFormat('F'));

        $baseQuery = $this->getPageTableQuery();

        // Totales históricos
        $totalHistoricoMaster = (clone $baseQuery)->where('agency_type_id', 1)->where('status', 'ACTIVO')->count();
        $totalHistoricoGeneral = (clone $baseQuery)->where('agency_type_id', 3)->where('status', 'ACTIVO')->count();

        // Datos Globales
        $totalGlobalAgencias = (clone $baseQuery)->whereIn('agency_type_id', [1, 3])->count();
        $agenciasActivas = (clone $baseQuery)->whereIn('agency_type_id', [1, 3])->where('status', 'ACTIVO')->count();
        $agenciasInactivas = (clone $baseQuery)->whereIn('agency_type_id', [1, 3])->where('status', 'INACTIVO')->count();

        // Totales del mes actual
        $totalMesActualAgenciasMaster = Agency::where('agency_type_id', 1)->where('status', 'ACTIVO')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $totalMesActualAgenciasGeneral = Agency::where('agency_type_id', 3)->where('status', 'ACTIVO')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        $masterMonthText = $totalMesActualAgenciasMaster === 0 ? 'Sin registros' : "+{$totalMesActualAgenciasMaster} este mes";
        $generalMonthText = $totalMesActualAgenciasGeneral === 0 ? 'Sin registros' : "+{$totalMesActualAgenciasGeneral} este mes";

        // Estilo común para las tarjetas tipo iOS
        $iosCardStyles = '
            relative overflow-hidden border-none shadow-sm transition-all duration-300 
            hover:shadow-md hover:-translate-y-1 group 
            bg-white/70 dark:bg-gray-900/50 backdrop-blur-xl 
            ring-1 ring-gray-200 dark:ring-white/10
        ';

        return [
            Stat::make('TOTAL AGENCIAS', $totalGlobalAgencias)
                ->description(new HtmlString("
                    <div class='mt-3 space-y-2'>
                        <div class='flex items-center justify-between text-[10px] uppercase tracking-widest font-semibold text-gray-400 dark:text-gray-500'>
                            <span>Distribución de Red</span>
                            <span class='text-primary-500'>{$nombreMes}</span>
                        </div>
                        <div class='flex items-center gap-3 p-2 rounded-2xl bg-gray-50/50 dark:bg-white/5'>
                            <div class='flex flex-col flex-1'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]'></div>
                                    <span class='text-xs font-medium text-gray-600 dark:text-gray-300'>Activas</span>
                                </div>
                                <span class='text-lg font-bold tracking-tight text-gray-900 dark:text-white'>{$agenciasActivas}</span>
                            </div>
                            <div class='w-px h-8 bg-gray-200 dark:bg-white/10'></div>
                            <div class='flex flex-col flex-1'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='w-1.5 h-1.5 rounded-full bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.5)]'></div>
                                    <span class='text-xs font-medium text-gray-600 dark:text-gray-300'>Inactivas</span>
                                </div>
                                <span class='text-lg font-bold tracking-tight text-gray-900 dark:text-white'>{$agenciasInactivas}</span>
                            </div>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-globe-alt')
                ->extraAttributes([
                    'class' => $iosCardStyles,
                    'style' => 'border-radius: 24px;',
                ]),

            Stat::make('TOTAL AGENCIAS MASTER', $totalHistoricoMaster)
                ->description(new HtmlString("
                    <div class='mt-3'>
                        <div class='text-[10px] uppercase tracking-widest font-semibold text-gray-400 dark:text-gray-500 mb-2'>
                            Crecimiento Mensual
                        </div>
                        <div class='inline-flex items-center px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20'>
                            <span class='text-[11px] font-bold text-blue-600 dark:text-blue-400'>
                                {$masterMonthText}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-users')
                ->extraAttributes([
                    'class' => $iosCardStyles,
                    'style' => 'border-radius: 24px;',
                ]),

            Stat::make('TOTALAGENCIAS GENERALES', $totalHistoricoGeneral)
                ->description(new HtmlString("
                    <div class='mt-3'>
                        <div class='text-[10px] uppercase tracking-widest font-semibold text-gray-400 dark:text-gray-500 mb-2'>
                            Crecimiento Mensual
                        </div>
                        <div class='inline-flex items-center px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20'>
                            <span class='text-[11px] font-bold text-emerald-600 dark:text-emerald-400'>
                                {$generalMonthText}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-check-badge')
                ->extraAttributes([
                    'class' => $iosCardStyles,
                    'style' => 'border-radius: 24px;',
                ]),
        ];
    }
}
