<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\Widgets;

use App\Models\TdevAgency;
use App\Models\TdevAgent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class TdevAgencyStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $levelTwo = TdevAgency::query()->levelTwo()->count();
        $levelThree = TdevAgency::query()->levelThree()->count();
        $agents = TdevAgent::query()->count();

        $cardBase = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border hover:shadow-lg hover:scale-[1.015]';

        return [
            Stat::make('Agencias nivel 2', (string) $levelTwo)
                ->description(new HtmlString(
                    '<div class="mt-1 text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Principales · red TDEV</div>'
                ))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info')
                ->extraAttributes([
                    'class' => $cardBase.' border-cyan-200/70 bg-gradient-to-br from-cyan-50/95 via-white to-teal-50/60 dark:border-cyan-700/40 dark:from-cyan-950/40 dark:via-gray-900/80 dark:to-teal-950/30 hover:shadow-cyan-500/15 hover:ring-2 hover:ring-cyan-400/40',
                ]),
            Stat::make('Agencias nivel 3', (string) $levelThree)
                ->description(new HtmlString(
                    '<div class="mt-1 text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Asociadas a nivel 2</div>'
                ))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('warning')
                ->extraAttributes([
                    'class' => $cardBase.' border-amber-200/70 bg-gradient-to-br from-amber-50/95 via-white to-orange-50/50 dark:border-amber-700/40 dark:from-amber-950/35 dark:via-gray-900/80 dark:to-orange-950/25 hover:shadow-amber-500/15 hover:ring-2 hover:ring-amber-400/40',
                ]),
            Stat::make('Agentes registrados', (string) $agents)
                ->description(new HtmlString(
                    '<div class="mt-1 text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Todos los niveles</div>'
                ))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success')
                ->extraAttributes([
                    'class' => $cardBase.' border-emerald-200/70 bg-gradient-to-br from-emerald-50/95 via-white to-teal-50/50 dark:border-emerald-700/40 dark:from-emerald-950/35 dark:via-gray-900/80 dark:to-teal-950/25 hover:shadow-emerald-500/15 hover:ring-2 hover:ring-emerald-400/40',
                ]),
        ];
    }
}
