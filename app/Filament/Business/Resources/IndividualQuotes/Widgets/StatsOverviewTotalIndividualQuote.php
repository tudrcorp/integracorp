<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Models\IndividualQuote;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewTotalIndividualQuote extends StatsOverviewWidget
{
    /**
     * @var array{year?: int, month?: int}
     */
    public array $statsFilters = [];

    protected ?string $heading = 'Total de cotizaciones';

    protected ?string $description = 'Resumen por año y mes seleccionado.';

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
        $year = (int) $value;
        $now = Carbon::now();
        $maxMonth = ($year === (int) $now->year) ? (int) $now->month : 12;

        $month = (int) ($this->statsFilters['month'] ?? $maxMonth);
        $this->statsFilters['month'] = max(1, min($maxMonth, $month));

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

        $totalAnio = IndividualQuote::query()
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->count();

        $totalMes = IndividualQuote::query()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        $cardAnio = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-[#26B4E8]/60 dark:border-[#26B4E8]/35 bg-gradient-to-br from-[#26B4E8]/25 via-white to-[#26B4E8]/10 dark:from-[#26B4E8]/20 dark:via-gray-900/80 dark:to-[#26B4E8]/10 hover:shadow-lg hover:shadow-[#26B4E8]/20 hover:scale-[1.02] hover:ring-2 hover:ring-[#26B4E8]/45 hover:border-[#26B4E8]/80 dark:hover:border-[#26B4E8]/55';
        $cardMes = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-[#84D3F6]/60 dark:border-[#84D3F6]/35 bg-gradient-to-br from-[#84D3F6]/25 via-white to-[#84D3F6]/10 dark:from-[#84D3F6]/20 dark:via-gray-900/80 dark:to-[#84D3F6]/10 hover:shadow-lg hover:shadow-[#84D3F6]/20 hover:scale-[1.02] hover:ring-2 hover:ring-[#84D3F6]/45 hover:border-[#84D3F6]/80 dark:hover:border-[#84D3F6]/55';

        $monthLabel = ucfirst($ref->locale(app()->getLocale())->translatedFormat('F'));

        return [
            Stat::make('Cotizaciones año '.$year, $totalAnio)
                ->description('Acumulado del año seleccionado')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->extraAttributes([
                    'class' => $cardAnio,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('Cotizaciones '.$monthLabel, $totalMes)
                ->description('Emitidas en el mes seleccionado')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info')
                ->extraAttributes([
                    'class' => $cardMes,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
