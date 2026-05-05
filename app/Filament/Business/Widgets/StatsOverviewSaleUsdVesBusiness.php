<?php

namespace App\Filament\Business\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class StatsOverviewSaleUsdVesBusiness extends StatsOverviewWidget
{
    /**
     * @var array{year?: int, month?: int}
     */
    public array $statsFilters = [];

    protected static ?int $sort = 1;

    protected ?string $heading = 'ANÁLISIS DE INGRESOS';

    protected ?string $description = 'Ventas del año y mes seleccionados (USD, VES y link de pago).';

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
        $nombreMes = ucfirst($ref->locale(app()->getLocale())->translatedFormat('F'));
        $anioActual = $year;

        $metrics = [
            [
                'id' => 'usd',
                'label' => 'VENTAS TOTALES USD',
                'column' => 'total_amount',
                'symbol' => 'US$',
                'icon' => 'heroicon-m-currency-dollar',
                'color' => 'info',
                'is_payment_link' => false,
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500',
                'labelClass' => 'text-info-600 dark:text-info-400',
                'badgeClass' => 'bg-info-100/90 text-info-700 dark:bg-info-900/40 dark:text-info-300',
            ],
            [
                'id' => 'ves',
                'label' => 'VENTAS TOTALES VES',
                'column' => 'pay_amount_ves',
                'symbol' => 'Bs.',
                'icon' => 'heroicon-m-banknotes',
                'color' => 'success',
                'is_payment_link' => false,
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500',
                'labelClass' => 'text-success-600 dark:text-success-400',
                'badgeClass' => 'bg-success-100/90 text-success-700 dark:bg-success-900/40 dark:text-success-300',
            ],
            [
                'id' => 'link',
                'label' => 'VENTAS TOTALES LINK DE PAGO',
                'column' => 'pay_amount_usd',
                'symbol' => 'US$',
                'icon' => 'heroicon-o-link',
                'color' => 'warning',
                'is_payment_link' => true,
                'cardClass' => 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500',
                'labelClass' => 'text-warning-600 dark:text-warning-400',
                'badgeClass' => 'bg-warning-100/90 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300',
            ],
        ];

        return array_map(function ($metric) use ($startOfYear, $endOfYear, $startOfMonth, $endOfMonth, $nombreMes, $anioActual) {
            $baseQuery = Sale::query()->where('is_payment_link', $metric['is_payment_link']);

            $totalAnioActual = (clone $baseQuery)
                ->whereBetween('created_at', [$startOfYear, $endOfYear])
                ->sum($metric['column']) ?? 0;

            $totalMesActual = (clone $baseQuery)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum($metric['column']) ?? 0;

            $valAnio = $metric['symbol'].' '.number_format($totalAnioActual, 2, ',', '.');
            $valMes = $metric['symbol'].' '.number_format($totalMesActual, 2, ',', '.');

            return Stat::make($metric['label'], $valAnio)
                ->description(self::descriptionHtml($anioActual, $nombreMes, $valMes, $metric['labelClass'], $metric['badgeClass']))
                ->descriptionIcon($metric['icon'])
                ->color($metric['color'])
                ->extraAttributes([
                    'class' => $metric['cardClass'],
                    'style' => 'min-height: 130px;',
                ]);
        }, $metrics);
    }

    protected static function descriptionHtml(int $anioActual, string $nombreMes, string $valMes, string $labelClass, string $badgeClass): HtmlString
    {
        $html = <<<HTML
        <div class='flex flex-col mt-1'>
            <span class='text-sm font-semibold uppercase tracking-wide {$labelClass}'>
                TOTAL AÑO {$anioActual}
            </span>
            <div class='flex items-center gap-2.5 mt-1.5'>
                <span class='px-2.5 py-1 text-sm font-bold rounded-lg {$badgeClass} shadow-sm'>
                    Mes seleccionado ({$nombreMes}):
                </span>
                <span class='text-base font-bold text-gray-900 dark:text-white'>
                    {$valMes}
                </span>
            </div>
        </div>
        HTML;

        return new HtmlString($html);
    }
}
