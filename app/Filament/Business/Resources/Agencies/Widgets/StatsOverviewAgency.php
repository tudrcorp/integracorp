<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Filament\Business\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewAgency extends StatsOverviewWidget
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
        return ListAgencies::class;
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
                View::make('filament.widgets.stats-overview-agency-filters')
                    ->viewData(fn (): array => [
                        'yearOptions' => $this->getYearSelectOptionsForAgencyStats(),
                        'monthOptions' => $this->getMonthSelectOptionsForAgencyStats(
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
    protected function getYearSelectOptionsForAgencyStats(): array
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
    protected function getMonthSelectOptionsForAgencyStats(?int $year = null): array
    {
        $now = Carbon::now();
        $maxMonth = ($year === (int) $now->year) ? (int) $now->month : 12;

        $options = [];
        $locale = app()->getLocale();
        for ($m = 1; $m <= $maxMonth; $m++) {
            $options[$m] = ucfirst(Carbon::createFromDate(2000, $m, 1)->locale($locale)->translatedFormat('F'));
        }

        return $options;
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
        $baseQuery = $this->getPageTableQuery();
        $rawYear = $this->statsFilters['year'] ?? null;
        $rawMonth = $this->statsFilters['month'] ?? null;

        $year = ($rawYear === null || $rawYear === '') ? null : (int) $rawYear;
        $month = ($rawMonth === null || $rawMonth === '') ? null : (int) $rawMonth;
        if ($month !== null) {
            $month = max(1, min(12, $month));
        }

        $now = Carbon::now();
        $yearForPeriod = (int) ($year ?? $now->year);
        $monthForPeriod = (int) ($month ?? $now->month);

        $nombreMes = $month === null
            ? 'Todos'
            : ucfirst(Carbon::createFromDate($yearForPeriod, $monthForPeriod, 1)->locale(app()->getLocale())->translatedFormat('F'));

        $scopeYear = function ($q) use ($year) {
            if ($year === null) {
                return clone $q;
            }

            $ref = Carbon::createFromDate($year, 1, 1);

            return (clone $q)->whereBetween('created_at', [$ref->copy()->startOfYear(), $ref->copy()->endOfYear()]);
        };

        $scopeMonth = function ($q) use ($year, $month, $scopeYear, $yearForPeriod, $monthForPeriod) {
            if ($month === null) {
                return $scopeYear($q);
            }

            $ref = Carbon::createFromDate((int) ($year ?? $yearForPeriod), $monthForPeriod, 1);

            return (clone $q)->whereBetween('created_at', [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()]);
        };

        $yearLabel = $year === null ? 'TODO' : (string) $year;

        // Totales globales (estáticos, no dependen del filtro).
        $totalGlobalAgencias = (clone $baseQuery)->whereIn('agency_type_id', [1, 3])->count();
        $totalGlobalMasterActivas = (clone $baseQuery)->where('agency_type_id', 1)->where('status', 'ACTIVO')->count();
        $totalGlobalGeneralActivas = (clone $baseQuery)->where('agency_type_id', 3)->where('status', 'ACTIVO')->count();

        // Totales anuales (dependen del filtro de año; sin año → total global).
        $totalYearAgencias = (clone $scopeYear($baseQuery))->whereIn('agency_type_id', [1, 3])->count();
        $totalYearMasterActivas = (clone $scopeYear($baseQuery))->where('agency_type_id', 1)->where('status', 'ACTIVO')->count();
        $totalYearGeneralActivas = (clone $scopeYear($baseQuery))->where('agency_type_id', 3)->where('status', 'ACTIVO')->count();

        // Distribución por estatus: año vs mes seleccionado (si mes vacío → mismo scope de año).
        $agenciasActivasYear = (clone $scopeYear($baseQuery))
            ->whereIn('agency_type_id', [1, 3])
            ->where('status', 'ACTIVO')
            ->count();
        $agenciasInactivasYear = (clone $scopeYear($baseQuery))
            ->whereIn('agency_type_id', [1, 3])
            ->where('status', 'INACTIVO')
            ->count();
        $agenciasActivasMes = (clone $scopeMonth($baseQuery))
            ->whereIn('agency_type_id', [1, 3])
            ->where('status', 'ACTIVO')
            ->count();
        $agenciasInactivasMes = (clone $scopeMonth($baseQuery))
            ->whereIn('agency_type_id', [1, 3])
            ->where('status', 'INACTIVO')
            ->count();

        $masterMesActivas = (clone $scopeMonth($baseQuery))->where('agency_type_id', 1)->where('status', 'ACTIVO')->count();
        $generalMesActivas = (clone $scopeMonth($baseQuery))->where('agency_type_id', 3)->where('status', 'ACTIVO')->count();

        $cardTotal = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';
        $cardMaster = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardGeneral = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';

        return [
            Stat::make('TOTAL AGENCIAS', (string) $totalGlobalAgencias)
                ->icon('fontisto-person')
                ->description(self::totalAgenciesDistributionDescription(
                    $yearLabel,
                    $totalYearAgencias,
                    $nombreMes,
                    $agenciasActivasYear,
                    $agenciasInactivasYear,
                    $agenciasActivasMes,
                    $agenciasInactivasMes
                ))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => $cardTotal,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('TOTAL AGENCIAS MASTER', (string) $totalGlobalMasterActivas)
                ->icon('fontisto-person')
                ->description(self::monthlyGrowthDescription(
                    $yearLabel,
                    $totalYearMasterActivas,
                    $nombreMes,
                    $masterMesActivas,
                    'text-info-600 dark:text-info-400',
                    'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300',
                    'master'
                ))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardMaster,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('TOTAL AGENCIAS GENERALES', (string) $totalGlobalGeneralActivas)
                ->icon('fontisto-person')
                ->description(self::monthlyGrowthDescription(
                    $yearLabel,
                    $totalYearGeneralActivas,
                    $nombreMes,
                    $generalMesActivas,
                    'text-primary-600 dark:text-primary-400',
                    'bg-primary-100/90 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300',
                    'general'
                ))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $cardGeneral,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }

    protected static function monthlyGrowthDescription(string $anioLabel, int $totalYear, string $nombreMes, int $valorMes, string $labelClass, string $badgeClass, string $unitLabel): HtmlString
    {
        $html = <<<HTML
        <div class="flex flex-col mt-1">
            <div class="inline-flex items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide {$labelClass}">
                    TOTAL AÑO {$anioLabel}
                </span>
                <span class="tabular-nums text-sm font-medium text-gray-950 dark:text-white">
                    {$totalYear}
                </span>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200">
                    Mes seleccionado ({$nombreMes})
                </span>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$valorMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{$unitLabel}</span>
                </div>
            </div>
        </div>
        HTML;

        return new HtmlString($html);
    }

    protected static function totalAgenciesDistributionDescription(string $anioLabel, int $totalYear, string $nombreMes, int $activasYear, int $inactivasYear, int $activasMes, int $inactivasMes): HtmlString
    {
        $html = <<<HTML
        <div class="flex flex-col mt-1">
            <div class="inline-flex items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-success-600 dark:text-success-400">
                    TOTAL AÑO {$anioLabel}
                </span>
                <span class="tabular-nums text-sm font-medium text-gray-950 dark:text-white">
                    {$totalYear}
                </span>
            </div>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-success-100/90 px-2.5 py-1 text-xs font-medium text-success-700 shadow-sm dark:bg-success-900/40 dark:text-success-300">
                        Activas
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$activasYear}
                    </span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="rounded-lg bg-rose-100/90 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm dark:bg-rose-900/40 dark:text-rose-300">
                        Inactivas
                    </span>
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$inactivasYear}
                    </span>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200">
                    Mes seleccionado ({$nombreMes})
                </span>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$activasMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">activas</span>
                </div>
                <div class="hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15" aria-hidden="true"></div>
                <div class="flex items-center gap-2.5">
                    <span class="tabular-nums text-sm font-medium text-gray-900 dark:text-white">
                        {$inactivasMes}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">inactivas</span>
                </div>
            </div>
        </div>
        HTML;

        return new HtmlString($html);
    }
}
