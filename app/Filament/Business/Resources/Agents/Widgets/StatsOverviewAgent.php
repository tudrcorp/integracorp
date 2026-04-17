<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Filament\Business\Resources\Agents\Pages\ListAgents;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewAgent extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    /**
     * @var array{year?: int, month?: int}
     */
    public array $statsFilters = [];

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    public function mount(): void
    {
        if ($this->statsFilters === []) {
            $now = Carbon::now();
            $this->statsFilters = [
                'year' => $now->year,
                'month' => $now->month,
            ];
        }
    }

    protected function getTablePage(): string
    {
        return ListAgents::class;
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
            ->afterHeader(
                View::make('filament.widgets.stats-overview-agent-filters')
                    ->viewData(fn (): array => [
                        'yearOptions' => $this->getYearSelectOptionsForAgentStats(),
                        'monthOptions' => $this->getMonthSelectOptionsForAgentStats(
                            (int) ($this->statsFilters['year'] ?? Carbon::now()->year)
                        ),
                        'year' => (int) ($this->statsFilters['year'] ?? Carbon::now()->year),
                    ])
            )
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer();
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
            'md' => 3,
        ];
    }

    public function updatedStatsFiltersYear(mixed $value): void
    {
        $year = (int) $value;
        $now = Carbon::now();
        $maxMonth = ($year === (int) $now->year) ? (int) $now->month : 12;

        $month = (int) ($this->statsFilters['month'] ?? $maxMonth);
        $this->statsFilters['month'] = max(1, min($maxMonth, $month));

        $this->cachedStats = null;
    }

    public function updatedStatsFiltersMonth(mixed $value): void
    {
        $this->statsFilters['month'] = (int) $value;
        $this->cachedStats = null;
    }

    /**
     * @return array<int|string, string>
     */
    protected function getYearSelectOptionsForAgentStats(): array
    {
        $current = (int) Carbon::now()->year;
        $options = [];
        for ($y = $current; $y >= $current - 5; $y--) {
            $options[$y] = (string) $y;
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    protected function getMonthSelectOptionsForAgentStats(?int $year = null): array
    {
        $year ??= (int) Carbon::now()->year;
        $now = Carbon::now();
        $maxMonth = ($year === (int) $now->year) ? (int) $now->month : 12;

        $options = [];
        $locale = app()->getLocale();
        for ($m = 1; $m <= $maxMonth; $m++) {
            $options[$m] = ucfirst(Carbon::createFromDate(2000, $m, 1)->locale($locale)->translatedFormat('F'));
        }

        return $options;
    }

    protected function getStats(): array
    {
        $year = (int) ($this->statsFilters['year'] ?? Carbon::now()->year);
        $month = (int) ($this->statsFilters['month'] ?? Carbon::now()->month);
        $month = max(1, min(12, $month));

        $ref = Carbon::createFromDate($year, $month, 1);
        $startOfYear = $ref->copy()->startOfYear();
        $endOfYear = $ref->copy()->endOfYear();
        $startOfMonth = $ref->copy()->startOfMonth();
        $endOfMonth = $ref->copy()->endOfMonth();
        $nombreMes = ucfirst($ref->locale(app()->getLocale())->translatedFormat('F'));

        $baseQuery = $this->getPageTableQuery();
        $totalGlobal = (clone $baseQuery)->count();
        $yearScoped = fn ($q) => (clone $q)->whereBetween('created_at', [$startOfYear, $endOfYear]);
        $monthScoped = fn ($q) => (clone $q)->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
        $yearToMonthScoped = fn ($q) => (clone $q)->whereBetween('created_at', [$startOfYear, $endOfMonth]);

        $totalAgentesYear = (clone $yearScoped($baseQuery))->count();

        $agentesActivosYear = (clone $yearScoped($baseQuery))->where('status', 'ACTIVO')->count();
        $agentesInactivosYear = (clone $yearScoped($baseQuery))->where('status', 'INACTIVO')->count();
        $agentesActivosMes = (clone $monthScoped($baseQuery))->where('status', 'ACTIVO')->count();
        $agentesInactivosMes = (clone $monthScoped($baseQuery))->where('status', 'INACTIVO')->count();

        $cantidadAgentesYear = (clone $yearScoped($baseQuery))->where('agent_type_id', 2)->count();
        $cantidadSubagentesYear = (clone $yearScoped($baseQuery))->where(function ($q) {
            $q->where('agent_type_id', '!=', 2)->orWhereNull('agent_type_id');
        })->count();
        $cantidadAgentesMes = (clone $monthScoped($baseQuery))->where('agent_type_id', 2)->count();
        $cantidadSubagentesMes = (clone $monthScoped($baseQuery))->where(function ($q) {
            $q->where('agent_type_id', '!=', 2)->orWhereNull('agent_type_id');
        })->count();

        $agentesVendenTdecYear = (clone $yearScoped($baseQuery))->where('tdec', 1)->count();
        $agentesVendenTdevYear = (clone $yearScoped($baseQuery))->where('tdev', 1)->count();
        $agentesVendenTdecMes = (clone $monthScoped($baseQuery))->where('tdec', 1)->count();
        $agentesVendenTdevMes = (clone $monthScoped($baseQuery))->where('tdev', 1)->count();

        $agentesVendenTdecGlobal = (clone $baseQuery)->where('tdec', 1)->count();
        $agentesVendenTdevGlobal = (clone $baseQuery)->where('tdev', 1)->count();
        $totalVentasTdecTdevYear = $agentesVendenTdecYear + $agentesVendenTdevYear;
        $totalVentasTdecTdevGlobal = $agentesVendenTdecGlobal + $agentesVendenTdevGlobal;

        $cardTotal = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';
        $cardTypes = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardSales = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';

        $descriptionHtml = <<<HTML
        <div class="flex flex-col mt-1">
            <div class="inline-flex items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-success-600 dark:text-success-400">
                    TOTAL AÑO {$year}
                </span>
                <span class="tabular-nums text-sm font-medium text-gray-950 dark:text-white">
                    {$totalAgentesYear}
                </span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-success-100/90 px-2.5 py-1 text-xs font-medium text-success-700 shadow-sm dark:bg-success-900/40 dark:text-success-300">
                        Operativos
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesActivosYear}
                    </span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-rose-100/90 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm dark:bg-rose-900/40 dark:text-rose-300">
                        Suspendidos
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesInactivosYear}
                    </span>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200">
                    Mes seleccionado ({$nombreMes})
                </span>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesActivosMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">operativos</span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesInactivosMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">suspendidos</span>
                </div>
            </div>
        </div>
        HTML;

        $descriptionTiposHtml = <<<HTML
        <div class="flex flex-col mt-1">
            <span class="text-xs font-medium uppercase tracking-wide text-info-600 dark:text-info-400">
                TOTAL AÑO {$year}
            </span>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-info-100/90 px-2.5 py-1 text-xs font-medium text-info-700 shadow-sm dark:bg-info-900/40 dark:text-info-300">
                        Agentes
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$cantidadAgentesYear}
                    </span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-primary-100/90 px-2.5 py-1 text-xs font-medium text-primary-700 shadow-sm dark:bg-primary-900/40 dark:text-primary-300">
                        Subagentes
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$cantidadSubagentesYear}
                    </span>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200">
                    Mes seleccionado ({$nombreMes})
                </span>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$cantidadAgentesMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">agentes</span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$cantidadSubagentesMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">subagentes</span>
                </div>
            </div>
        </div>
        HTML;

        $descriptionVentasHtml = <<<HTML
        <div class="flex flex-col mt-1">
            <div class="inline-flex items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">
                    TOTAL AÑO {$year}
                </span>
                <span class="tabular-nums text-sm font-medium text-gray-950 dark:text-white">
                    {$totalVentasTdecTdevYear}
                </span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-primary-100/90 px-2.5 py-1 text-xs font-medium text-primary-700 shadow-sm dark:bg-primary-900/40 dark:text-primary-300">
                        TDEC
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesVendenTdecYear}
                    </span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-success-100/90 px-2.5 py-1 text-xs font-medium text-success-700 shadow-sm dark:bg-success-900/40 dark:text-success-300">
                        TDEV
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesVendenTdevYear}
                    </span>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200">
                    Mes seleccionado ({$nombreMes})
                </span>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesVendenTdecMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">TDEC</span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$agentesVendenTdevMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">TDEV</span>
                </div>
            </div>
        </div>
        HTML;

        $previousYear = $year - 1;
        $previousYearRef = Carbon::createFromDate($previousYear, 1, 1);
        $previousYearEnd = $previousYearRef->copy()->endOfYear();

        // Base: total acumulado al cierre del año anterior (<= 31/12 del año previo).
        $totalAgentesYSubagentesPreviousYear = (clone $baseQuery)
            ->where('created_at', '<=', $previousYearEnd)
            ->count();

        // Nuevos: creados en el año actual (acumulado hasta el mes seleccionado).
        $totalAgentesYSubagentesNewInYear = (clone $yearToMonthScoped($baseQuery))->count();

        $networkExpansionRatioPercent = self::calculateNetworkExpansionRatioPercent(
            $totalAgentesYSubagentesNewInYear,
            $totalAgentesYSubagentesPreviousYear
        );

        $yoyValue = $networkExpansionRatioPercent === null
            ? '—'
            : sprintf('%.1f%%', $networkExpansionRatioPercent);

        $yoyDescriptionIcon = match (true) {
            $networkExpansionRatioPercent === null => 'heroicon-m-minus',
            default => 'heroicon-m-arrow-trending-up',
        };

        return [
            Stat::make('ESTADO DE AGENTES', (string) $totalGlobal)
                ->icon('fontisto-person')
                ->description(new HtmlString($descriptionHtml))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => $cardTotal,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('AGENTES Y SUBAGENTES', $yoyValue)
                ->icon('heroicon-m-user-group')
                ->description(new HtmlString($descriptionTiposHtml))
                ->descriptionIcon($yoyDescriptionIcon)
                ->color('info')
                ->extraAttributes([
                    'class' => $cardTypes,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('VENTAS TDEC / TDEV', (string) $totalVentasTdecTdevGlobal)
                ->icon('heroicon-m-currency-dollar')
                ->description(new HtmlString($descriptionVentasHtml))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary')
                ->extraAttributes([
                    'class' => $cardSales,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }

    protected static function calculateNetworkExpansionRatioPercent(int $newInYear, int $basePreviousYear): ?float
    {
        if ($basePreviousYear <= 0) {
            return null;
        }

        return ($newInYear / $basePreviousYear) * 100;
    }

    protected static function calculateYearOverYearPercentChange(int $current, int $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    /**
     * @return array<string, int>
     */
    protected static function buildYearToMonthCumulativeSparkline(mixed $baseQuery, int $year, int $month): array
    {
        $month = max(1, min(12, $month));

        $labels = [];
        $values = [];

        $locale = app()->getLocale();
        $yearStart = Carbon::createFromDate($year, 1, 1)->startOfYear();

        for ($m = 1; $m <= $month; $m++) {
            $end = Carbon::createFromDate($year, $m, 1)->endOfMonth();

            $labels[] = Carbon::createFromDate(2000, $m, 1)->locale($locale)->translatedFormat('M');
            $values[] = (clone $baseQuery)->whereBetween('created_at', [$yearStart, $end])->count();
        }

        return array_combine($labels, $values) ?: [];
    }
}
