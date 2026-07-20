<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\Affiliate;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewPlan extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    /**
     * @var array{year?: int, month?: int}
     */
    public array $statsFilters = [];

    protected ?string $heading = 'ANÁLISIS DE AFILIACIONES POR PLAN';

    protected ?string $description = 'Distribución de afiliaciones según el tipo de suscripción. Filtra por año y mes (o todo el año).';

    protected function getTablePage(): string
    {
        return ListAffiliations::class;
    }

    public function mount(): void
    {
        if ($this->statsFilters === []) {
            $now = Carbon::now();
            $this->statsFilters = [
                'year' => $now->year,
                'month' => 0,
            ];
        }
    }

    public function getSectionContentComponent(): Section
    {
        return Section::make()
            ->heading($this->getHeading())
            ->description($this->getDescription())
            ->afterHeader(
                View::make('filament.widgets.stats-overview-filters')
                    ->viewData(fn (): array => [
                        'yearOptions' => $this->getYearSelectOptions(),
                        'monthOptions' => $this->getMonthSelectOptions((int) ($this->statsFilters['year'] ?? Carbon::now()->year)),
                        'year' => (int) ($this->statsFilters['year'] ?? Carbon::now()->year),
                    ])
            )
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer();
    }

    public function updatedStatsFiltersYear($value): void
    {
        $this->statsFilters['year'] = (int) $value;
        $this->cachedStats = null;
    }

    public function updatedStatsFiltersMonth($value): void
    {
        $this->statsFilters['month'] = (int) $value;
        $this->cachedStats = null;
    }

    /**
     * @return array<int|string, string>
     */
    protected function getYearSelectOptions(): array
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
    protected function getMonthSelectOptions(?int $year = null): array
    {
        $year ??= (int) Carbon::now()->year;
        $now = Carbon::now();
        $maxMonth = ($year === (int) $now->year) ? (int) $now->month : 12;

        $options = ['0' => 'Todo el año'];
        $locale = app()->getLocale();
        for ($m = 1; $m <= $maxMonth; $m++) {
            $options[(string) $m] = ucfirst(Carbon::createFromDate(2000, $m, 1)->locale($locale)->translatedFormat('F'));
        }

        return $options;
    }

    protected function getStats(): array
    {
        $year = (int) ($this->statsFilters['year'] ?? Carbon::now()->year);
        $month = (int) ($this->statsFilters['month'] ?? 0);
        $month = max(0, min(12, $month));

        $ref = Carbon::createFromDate($year, max(1, $month), 1);

        $start = $month === 0 ? $ref->copy()->startOfYear() : $ref->copy()->startOfMonth();
        $end = $month === 0 ? $ref->copy()->endOfYear() : $ref->copy()->endOfMonth();

        $periodLabel = $month === 0
            ? (string) $year
            : ucfirst($ref->locale(app()->getLocale())->translatedFormat('F'))." {$year}";

        $countForPlan = fn (int $planId): int => Affiliate::query()
            ->where('plan_id', $planId)
            ->where('status', 'ACTIVO')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $cardPlan1 = 'cursor-default overflow-hidden rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20';
        $cardPlan2 = 'cursor-default overflow-hidden rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20';
        $cardPlan3 = 'cursor-default overflow-hidden rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20';

        return [
            Stat::make('PLAN INICIAL', $countForPlan(1).' Afiliados')
                ->description("Plan básico · {$periodLabel}")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardPlan1,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('PLAN IDEAL', $countForPlan(2).' Afiliados')
                ->description("Asistencia Médica · {$periodLabel}")
                ->descriptionIcon('heroicon-m-star')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $cardPlan2,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('PLAN ESPECIAL', $countForPlan(3).' Afiliados')
                ->description("Emergencias Médicas · {$periodLabel}")
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('planEspecial')
                ->extraAttributes([
                    'class' => $cardPlan3,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
