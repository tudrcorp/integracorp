<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Business\Resources\Agents\Pages\ListAgents;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\Agent;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewAgent extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.widgets.stats-overview-agent-ios';

    protected ?string $heading = null;

    protected ?string $description = null;

    protected function getTablePage(): string
    {
        return ListAgents::class;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components($this->getCachedStats())
            ->columns($this->getColumns());
    }

    /**
     * Grid de 2 columnas desde `md`: la única stat ocupa 1 celda ≈ 50% del ancho.
     * Por debajo de `md` una columna → tarjeta a ancho completo.
     *
     * @return array<string, int>
     */
    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }

    protected function getStats(): array
    {
        $totalAgentes = Agent::count();
        $agentesActivos = Agent::where('status', 'ACTIVO')->count();
        $agentesInactivos = Agent::where('status', 'INACTIVO')->count();

        return [
            Stat::make('Estatus de agentes', $totalAgentes)
                ->icon('heroicon-m-users')
                ->description(new HtmlString("
                    <div class='mt-2 w-full min-w-0'>
                        <div class='flex w-full min-w-0 items-stretch gap-3 rounded-2xl border border-zinc-200/60 bg-white/35 p-3 shadow-inner dark:border-white/[0.08] dark:bg-zinc-950/40'>
                            <div class='flex min-w-0 flex-1 flex-col'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.45)]'></div>
                                    <span class='text-xs font-medium text-zinc-600 dark:text-zinc-300'>Activos</span>
                                </div>
                                <span class='text-lg font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white'>{$agentesActivos}</span>
                            </div>
                            <div class='h-auto w-px shrink-0 self-stretch bg-zinc-200/80 dark:bg-white/10'></div>
                            <div class='flex min-w-0 flex-1 flex-col'>
                                <div class='flex items-center gap-1.5'>
                                    <div class='h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.45)]'></div>
                                    <span class='text-xs font-medium text-zinc-600 dark:text-zinc-300'>Inactivos</span>
                                </div>
                                <span class='text-lg font-bold tabular-nums tracking-tight text-zinc-900 dark:text-white'>{$agentesInactivos}</span>
                            </div>
                        </div>
                    </div>
                "))
                ->color('primary'),
        ];
    }
}
