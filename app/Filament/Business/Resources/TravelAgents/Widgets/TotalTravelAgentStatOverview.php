<?php

namespace App\Filament\Business\Resources\TravelAgents\Widgets;

use App\Filament\Business\Resources\TravelAgents\Pages\ListTravelAgents;
use Carbon\Carbon;
use Filament\Schemas\Schema;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class TotalTravelAgentStatOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.widgets.stats-overview-travel-agent-glass';

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListTravelAgents::class;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components($this->getCachedStats())
            ->columns($this->getColumns());
    }

    protected function getColumns(): int|array|null
    {
        return 1;
    }

    protected function getStats(): array
    {
        $baseQuery = $this->getPageTableQuery();

        $total = (clone $baseQuery)->count();

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $nombreMes = ucfirst($now->translatedFormat('F'));

        $altasEsteMes = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $anteriores = max(0, $total - $altasEsteMes);

        return [
            Stat::make('Agentes de viaje', $total)
                ->descriptionIcon('heroicon-m-user-group')
                ->description(new HtmlString("
                    <div class='mt-2 w-full min-w-0'>
                        <div class='mb-2 text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400'>
                            Total según listado y filtros · {$nombreMes}
                        </div>
                        <div class='grid w-full min-w-0 grid-cols-2 gap-3 rounded-2xl border border-zinc-200/60 bg-white/35 p-3 shadow-inner backdrop-blur-md dark:border-white/[0.08] dark:bg-zinc-950/40'>
                            <div class='flex min-w-0 flex-col'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='h-1.5 w-1.5 shrink-0 rounded-full bg-violet-500 shadow-[0_0_8px_rgba(139,92,246,0.5)]'></div>
                                    <span class='text-xs font-medium text-zinc-600 dark:text-zinc-300'>Altas este mes</span>
                                </div>
                                <span class='text-lg font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white'>{$altasEsteMes}</span>
                            </div>
                            <div class='flex min-w-0 flex-col border-l border-zinc-200/80 pl-3 dark:border-white/10'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400 shadow-[0_0_6px_rgba(148,163,184,0.45)]'></div>
                                    <span class='text-xs font-medium text-zinc-600 dark:text-zinc-300'>Anteriores</span>
                                </div>
                                <span class='text-lg font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white'>{$anteriores}</span>
                            </div>
                        </div>
                    </div>
                "))
                ->color('primary'),
        ];
    }
}
