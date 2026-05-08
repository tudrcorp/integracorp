<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Widgets;

use App\Filament\Business\Resources\BusinessAppointments\Pages\ListBusinessAppointments;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Carbon\CarbonImmutable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class BusinessAppointmentsKpiOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListBusinessAppointments::class;
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
     * @return int|array<string, int|null>
     */
    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $summary = self::buildSummary((clone $this->getPageTableQuery()));

        $conversionRate = (float) $summary['conversion_rate'];
        $conversionLabel = number_format($conversionRate, 1).' %';
        $conversionTone = $conversionRate >= 60.0 ? 'success' : ($conversionRate >= 35.0 ? 'warning' : 'danger');

        $cardSuccess = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';
        $cardInfo = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardWarning = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-warning-200/60 dark:border-warning-700/50 bg-gradient-to-br from-warning-50/90 via-white to-warning-50/50 dark:from-warning-950/40 dark:via-gray-900/80 dark:to-warning-900/20 hover:shadow-lg hover:shadow-warning-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-warning-400/50 hover:border-warning-300 dark:hover:border-warning-500';
        $cardGray = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 bg-gradient-to-br from-gray-50/90 via-white to-gray-50/50 dark:from-gray-950/40 dark:via-gray-900/80 dark:to-gray-900/20 hover:shadow-lg hover:shadow-gray-500/10 hover:scale-[1.02] hover:ring-2 hover:ring-gray-400/30 hover:border-gray-300 dark:hover:border-gray-500';

        $descConversion = new HtmlString(
            "<div class=\"flex flex-col mt-1\">
                <div class=\"inline-flex items-center gap-2\">
                    <span class=\"text-xs font-medium uppercase tracking-wide text-{$conversionTone}-600 dark:text-{$conversionTone}-400\">
                        ATENDIDAS / TOTALES
                    </span>
                </div>
                <div class=\"mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2\">
                    <div class=\"flex items-center gap-2.5\">
                        <span class=\"rounded-lg bg-{$conversionTone}-100/90 px-2.5 py-1 text-xs font-medium text-{$conversionTone}-700 shadow-sm dark:bg-{$conversionTone}-900/40 dark:text-{$conversionTone}-300\">
                            Atendidas
                        </span>
                        <span class=\"tabular-nums text-sm font-medium text-gray-900 dark:text-white\">
                            {$summary['attended']}
                        </span>
                    </div>
                    <div class=\"hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15\" aria-hidden=\"true\"></div>
                    <div class=\"flex items-center gap-2.5\">
                        <span class=\"rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200\">
                            Total
                        </span>
                        <span class=\"tabular-nums text-sm font-medium text-gray-900 dark:text-white\">
                            {$summary['total']}
                        </span>
                    </div>
                </div>
            </div>"
        );

        $descVolumen = new HtmlString(
            "<div class=\"flex flex-col mt-1\">
                <div class=\"inline-flex items-center gap-2\">
                    <span class=\"text-xs font-medium uppercase tracking-wide text-success-600 dark:text-success-400\">
                        ALTAS (ALTA / CREATED_AT)
                    </span>
                </div>
                <div class=\"mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2\">
                    <div class=\"flex items-center gap-2.5\">
                        <span class=\"rounded-lg bg-success-100/90 px-2.5 py-1 text-xs font-medium text-success-700 shadow-sm dark:bg-success-900/40 dark:text-success-300\">
                            Mes
                        </span>
                        <span class=\"tabular-nums text-sm font-medium text-gray-900 dark:text-white\">
                            {$summary['new_this_month']}
                        </span>
                    </div>
                    <div class=\"hidden h-6 w-px shrink-0 bg-zinc-200/80 sm:block dark:bg-white/15\" aria-hidden=\"true\"></div>
                    <div class=\"flex items-center gap-2.5\">
                        <span class=\"rounded-lg bg-gray-100/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800/60 dark:text-gray-200\">
                            Semana
                        </span>
                        <span class=\"tabular-nums text-sm font-medium text-gray-900 dark:text-white\">
                            {$summary['new_this_week']}
                        </span>
                    </div>
                </div>
            </div>"
        );

        return [
            Stat::make('TASA DE CONVERSIÓN', $conversionLabel)
                ->icon('heroicon-m-chart-bar-square')
                ->description($descConversion)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => $conversionTone === 'success'
                        ? $cardSuccess
                        : ($conversionTone === 'warning' ? $cardWarning : $cardWarning),
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('AGENDADAS ACTIVAS', (string) $summary['scheduled'])
                ->icon('heroicon-m-calendar-days')
                ->description(new HtmlString("Atendidas <span class=\"tabular-nums font-semibold\">{$summary['attended']}</span> · Canceladas <span class=\"tabular-nums font-semibold\">{$summary['cancelled']}</span>"))
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => $cardInfo,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('VOLUMEN NUEVO', (string) $summary['new_this_month'])
                ->icon('heroicon-m-arrow-trending-up')
                ->description($descVolumen)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => $cardSuccess,
                    'style' => 'min-height: 130px;',
                ]),

            Stat::make('TOTAL CITAS', (string) $summary['total'])
                ->icon('heroicon-m-clipboard-document-list')
                ->description(new HtmlString('Base total con filtros actuales'))
                ->descriptionIcon('heroicon-m-funnel')
                ->color('gray')
                ->extraAttributes([
                    'class' => $cardGray,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     attended: int,
     *     cancelled: int,
     *     scheduled: int,
     *     conversion_rate: float,
     *     new_this_month: int,
     *     new_this_week: int
     * }
     */
    public static function buildSummary(Builder $query): array
    {
        $now = CarbonImmutable::now();
        $total = (clone $query)->count();
        $attended = (clone $query)->where('status', 'ATENDIDA')->count();
        $cancelled = (clone $query)->where('status', 'CANCELADA')->count();
        $scheduled = (clone $query)
            ->whereIn('status', ['PENDIENTE', 'REAGENDADA', 'ATENDIDA'])
            ->count();

        $newThisMonth = (clone $query)
            ->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()])
            ->count();

        $newThisWeek = (clone $query)
            ->whereBetween('created_at', [$now->startOfWeek(), $now->endOfWeek()])
            ->count();

        return [
            'total' => $total,
            'attended' => $attended,
            'cancelled' => $cancelled,
            'scheduled' => $scheduled,
            'conversion_rate' => $total > 0 ? ($attended / $total) * 100 : 0.0,
            'new_this_month' => $newThisMonth,
            'new_this_week' => $newThisWeek,
        ];
    }
}
