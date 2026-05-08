<?php

namespace App\Filament\Business\Resources\TravelAgents\Widgets;

use App\Filament\Business\Resources\TravelAgents\Pages\ListTravelAgents;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class TotalTravelAgentStatOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

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
            ->components([
                $this->getSectionContentComponent(),
            ]);
    }

    public function getSectionContentComponent(): Section
    {
        return Section::make()
            ->heading($this->getHeading())
            ->description($this->getDescription())
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer();
    }

    protected function getColumns(): int|array|null
    {
        return 3;
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

        $cardTotal = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';

        return [
            Stat::make('AGENTES DE VIAJE', (string) $total)
                ->descriptionIcon('heroicon-m-user-group')
                ->description(new HtmlString("
                    <div class='mt-2 flex w-full min-w-0 flex-col gap-2'>
                        <div class='text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400'>
                            Total según listado y filtros · {$nombreMes}
                        </div>

                        <div class='flex flex-wrap items-center gap-x-4 gap-y-2'>
                            <div class='flex items-center gap-2.5'>
                                <span class='rounded-lg bg-success-100/90 px-2.5 py-1 text-sm font-medium text-success-700 shadow-sm dark:bg-success-900/40 dark:text-success-300'>
                                    Altas este mes
                                </span>
                                <span class='tabular-nums text-base font-medium text-gray-900 dark:text-white'>
                                    {$altasEsteMes}
                                </span>
                            </div>

                            <div class='hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15' aria-hidden='true'></div>

                            <div class='flex items-center gap-2.5'>
                                <span class='rounded-lg bg-rose-100/90 px-2.5 py-1 text-sm font-medium text-rose-700 shadow-sm dark:bg-rose-900/40 dark:text-rose-300'>
                                    Anteriores
                                </span>
                                <span class='tabular-nums text-base font-medium text-gray-900 dark:text-white'>
                                    {$anteriores}
                                </span>
                            </div>
                        </div>
                    </div>
                "))
                ->color('planCorp')
                ->extraAttributes([
                    'class' => "{$cardTotal} col-span-full",
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
