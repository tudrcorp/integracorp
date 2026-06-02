<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\IndividualQuote;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewIndividualQuote extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected const EXECUTED_STATUS = 'EJECUTADA';

    // protected array $tableColumnSearches = [];

    /**
     * @var array{year?: int, month?: int}
     */
    public array $statsFilters = [];

    protected ?string $heading = 'ANÁLISIS DE REGISTROS DE COTIZACIÓN INDIVIDUAL EMITIDAS';

    protected ?string $description = 'Distribución de cotizaciones según el tipo de suscripción. Filtra por año y mes (o todo el año).';

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

    // protected function getTablePage(): string
    // {
    //     return ListIndividualQuotes::class;
    // }

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

        /**
         * Lógica de obtención de datos:
         * Obtenemos el conteo total histórico y el conteo del mes actual para cada plan.
         */
        $plans = [
            '1' => [
                'label' => 'COTIZACIONES PLAN INICIAL',
                'color' => 'info',
                'icon' => 'heroicon-m-check-badge',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-[#84D3F6]/60 dark:border-[#84D3F6]/35 bg-gradient-to-br from-[#84D3F6]/25 via-white to-[#84D3F6]/10 dark:from-[#84D3F6]/20 dark:via-gray-900/80 dark:to-[#84D3F6]/10 hover:shadow-lg hover:shadow-[#84D3F6]/20 hover:scale-[1.02] hover:ring-2 hover:ring-[#84D3F6]/45 hover:border-[#84D3F6]/80 dark:hover:border-[#84D3F6]/55',
            ],
            '2' => [
                'label' => 'COTIZACIONES PLAN IDEAL',
                'color' => 'info',
                'icon' => 'heroicon-m-star',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-[#26B4E8]/60 dark:border-[#26B4E8]/35 bg-gradient-to-br from-[#26B4E8]/25 via-white to-[#26B4E8]/10 dark:from-[#26B4E8]/20 dark:via-gray-900/80 dark:to-[#26B4E8]/10 hover:shadow-lg hover:shadow-[#26B4E8]/20 hover:scale-[1.02] hover:ring-2 hover:ring-[#26B4E8]/45 hover:border-[#26B4E8]/80 dark:hover:border-[#26B4E8]/55',
            ],
            '3' => [
                'label' => 'COTIZACIONES PLAN ESPECIAL',
                'color' => 'info',
                'icon' => 'heroicon-m-sparkles',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-[#2D89CA]/60 dark:border-[#2D89CA]/35 bg-gradient-to-br from-[#2D89CA]/25 via-white to-[#2D89CA]/10 dark:from-[#2D89CA]/20 dark:via-gray-900/80 dark:to-[#2D89CA]/10 hover:shadow-lg hover:shadow-[#2D89CA]/20 hover:scale-[1.02] hover:ring-2 hover:ring-[#2D89CA]/45 hover:border-[#2D89CA]/80 dark:hover:border-[#2D89CA]/55',
            ],
            'CM' => [
                'label' => 'COTIZACIONES MULTIPLAN',
                'color' => 'info',
                'icon' => 'heroicon-m-building-office',
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-[#26B4E8]/60 dark:border-[#26B4E8]/35 bg-gradient-to-br from-[#26B4E8]/25 via-white to-[#26B4E8]/10 dark:from-[#26B4E8]/20 dark:via-gray-900/80 dark:to-[#26B4E8]/10 hover:shadow-lg hover:shadow-[#26B4E8]/20 hover:scale-[1.02] hover:ring-2 hover:ring-[#26B4E8]/45 hover:border-[#26B4E8]/80 dark:hover:border-[#26B4E8]/55',
            ],
        ];

        $stats = [];

        foreach ($plans as $key => $config) {
            $emitidas = IndividualQuote::where('plan', $key)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $ejecutadas = IndividualQuote::where('plan', $key)
                ->where('status', self::EXECUTED_STATUS)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $efectividad = $emitidas > 0
                ? round(($ejecutadas / $emitidas) * 100, 1)
                : 0.0;

            $stats[] = Stat::make($config['label'], $emitidas)
                ->description("Periodo: {$periodLabel}")
                ->descriptionIcon($config['icon'])
                ->color($config['color'])
                ->extraAttributes([
                    'class' => $config['cardClass'],
                    'style' => 'min-height: 105px;',
                ])
                ->value(new HtmlString((string) $emitidas))
                ->description(new HtmlString(
                    "<div class='flex flex-col gap-1'>
                        <span class='text-sm'>Periodo: {$periodLabel}</span>
                        <div class='flex flex-wrap items-center gap-x-3 gap-y-1 text-lg   text-gray-600 dark:text-gray-300'>
                            <span><strong>Ejecutadas:</strong> <span class='text-lg'>{$ejecutadas}</span></span>
                            <span><strong>Efectividad:</strong> <span class='text-lg'>{$efectividad}%</span></span>
                        </div>
                    </div>"
                ));
        }

        return $stats;
    }
}
