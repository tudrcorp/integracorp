<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Pages\ListProspectAgents;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewCapacitacion extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListProspectAgents::class;
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

    /**
     * @return array<string, int>
     */
    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'md' => 3,
        ];
    }

    protected function getStats(): array
    {
        $baseQuery = $this->getPageTableQuery();

        $total = (clone $baseQuery)->count();

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $locale = app()->getLocale();
        $nombreMes = ucfirst($now->locale($locale)->translatedFormat('F'));

        $altasEsteMes = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $anteriores = max(0, $total - $altasEsteMes);

        $cardTotal = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';

        $descriptionHtml = <<<HTML
        <div class="flex flex-col mt-1">
            <div class="inline-flex items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-success-600 dark:text-success-400">
                    MES EN CURSO ({$nombreMes})
                </span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-success-100/90 px-2.5 py-1 text-xs font-medium text-success-700 shadow-sm dark:bg-success-900/40 dark:text-success-300">
                        Altas este mes
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$altasEsteMes}
                    </span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200">
                        Anteriores
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$anteriores}
                    </span>
                </div>
            </div>
        </div>
        HTML;

        return [
            Stat::make('PROSPECTOS', (string) $total)
                ->icon('heroicon-m-user-plus')
                ->description(new HtmlString($descriptionHtml))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => $cardTotal,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
