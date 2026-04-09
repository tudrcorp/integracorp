<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Filament\Business\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\Agency;
use Carbon\Carbon;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewAgency extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.widgets.stats-overview-agency-glass';

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListAgencies::class;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components($this->getCachedStats())
            ->columns($this->getColumns());
    }

    /**
     * Las tres métricas en una sola fila en todos los anchos de vista.
     */
    protected function getColumns(): int|array|null
    {
        return 3;
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

        $insetPanel = 'rounded-2xl border border-zinc-200/55 bg-white/45 p-2 shadow-inner backdrop-blur-md dark:border-white/[0.1] dark:bg-zinc-950/40';
        $pillBlue = 'inline-flex items-center rounded-full border border-blue-400/35 bg-blue-500/18 px-3 py-1 backdrop-blur-md dark:border-blue-400/25 dark:bg-blue-500/12';
        $pillEmerald = 'inline-flex items-center rounded-full border border-emerald-400/35 bg-emerald-500/18 px-3 py-1 backdrop-blur-md dark:border-emerald-400/25 dark:bg-emerald-500/12';

        return [
            Stat::make('TOTAL AGENCIAS', $totalGlobalAgencias)
                ->description(new HtmlString("
                    <div class='mt-3 space-y-2'>
                        <div class='flex items-center justify-between text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400'>
                            <span>Distribución de Red</span>
                            <span class='text-primary-600 dark:text-primary-400'>{$nombreMes}</span>
                        </div>
                        <div class='flex items-center gap-3 {$insetPanel}'>
                            <div class='flex flex-1 flex-col'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.55)]'></div>
                                    <span class='text-xs font-medium text-zinc-600 dark:text-zinc-300'>Activas</span>
                                </div>
                                <span class='text-lg font-bold tracking-tight text-zinc-900 tabular-nums dark:text-white'>{$agenciasActivas}</span>
                            </div>
                            <div class='h-8 w-px shrink-0 bg-zinc-200/80 dark:bg-white/15'></div>
                            <div class='flex flex-1 flex-col'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0.5)]'></div>
                                    <span class='text-xs font-medium text-zinc-600 dark:text-zinc-300'>Inactivas</span>
                                </div>
                                <span class='text-lg font-bold tracking-tight text-zinc-900 tabular-nums dark:text-white'>{$agenciasInactivas}</span>
                            </div>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-globe-alt'),

            Stat::make('TOTAL AGENCIAS MASTER', $totalHistoricoMaster)
                ->description(new HtmlString("
                    <div class='mt-3'>
                        <div class='mb-2 text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400'>
                            Crecimiento Mensual
                        </div>
                        <div class='{$pillBlue}'>
                            <span class='text-[11px] font-bold text-blue-700 dark:text-blue-300'>
                                {$masterMonthText}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-users'),

            Stat::make('TOTAL AGENCIAS GENERALES', $totalHistoricoGeneral)
                ->description(new HtmlString("
                    <div class='mt-3'>
                        <div class='mb-2 text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400'>
                            Crecimiento Mensual
                        </div>
                        <div class='{$pillEmerald}'>
                            <span class='text-[11px] font-bold text-emerald-700 dark:text-emerald-300'>
                                {$generalMonthText}
                            </span>
                        </div>
                    </div>
                "))
                ->descriptionIcon('heroicon-m-check-badge'),
        ];
    }
}
