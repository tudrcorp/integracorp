<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Pages\ListProspectAgents;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
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
     * Total + los cinco estatus del embudo.
     *
     * @return array<string, int>
     */
    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
            '2xl' => 6,
        ];
    }

    protected function getStats(): array
    {
        $baseQuery = $this->getPageTableQuery();

        $total = (clone $baseQuery)->count();
        $captacionProspeccion = (clone $baseQuery)
            ->whereIn('status', ProspectAgentLabels::statusKeysIncludingLegacy(ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION))
            ->count();
        $contactoInicial = (clone $baseQuery)
            ->whereIn('status', ProspectAgentLabels::statusKeysIncludingLegacy(ProspectAgentLabels::STATUS_CONTACTO_INICIAL))
            ->count();
        $enProcesoNegociacion = (clone $baseQuery)
            ->whereIn('status', ProspectAgentLabels::statusKeysIncludingLegacy(ProspectAgentLabels::STATUS_EN_PROCESO_NEGOCIACION))
            ->count();
        $aliadoActivo = (clone $baseQuery)
            ->whereIn('status', ProspectAgentLabels::statusKeysIncludingLegacy(ProspectAgentLabels::STATUS_ALIADO_ACTIVO))
            ->count();
        $inactivo = (clone $baseQuery)
            ->whereIn('status', ProspectAgentLabels::statusKeysIncludingLegacy(ProspectAgentLabels::STATUS_INACTIVO))
            ->count();

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
        $cardCaptacion = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 bg-gradient-to-br from-gray-50/90 via-white to-gray-50/50 dark:from-gray-950/40 dark:via-gray-900/80 dark:to-gray-900/20 hover:shadow-lg hover:shadow-gray-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-gray-400/50 hover:border-gray-300 dark:hover:border-gray-500';
        $cardContacto = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-info-200/60 dark:border-info-700/50 bg-gradient-to-br from-info-50/90 via-white to-info-50/50 dark:from-info-950/40 dark:via-gray-900/80 dark:to-info-900/20 hover:shadow-lg hover:shadow-info-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-info-400/50 hover:border-info-300 dark:hover:border-info-500';
        $cardProceso = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-primary-200/60 dark:border-primary-700/50 bg-gradient-to-br from-primary-50/90 via-white to-primary-50/50 dark:from-primary-950/40 dark:via-gray-900/80 dark:to-primary-900/20 hover:shadow-lg hover:shadow-primary-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-primary-400/50 hover:border-primary-300 dark:hover:border-primary-500';
        $cardAliado = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-success-200/60 dark:border-success-700/50 bg-gradient-to-br from-success-50/90 via-white to-success-50/50 dark:from-success-950/40 dark:via-gray-900/80 dark:to-success-900/20 hover:shadow-lg hover:shadow-success-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-success-400/50 hover:border-success-300 dark:hover:border-success-500';
        $cardInactivo = 'cursor-default overflow-hidden transition-all duration-300 rounded-2xl border border-danger-200/60 dark:border-danger-700/50 bg-gradient-to-br from-danger-50/90 via-white to-danger-50/50 dark:from-danger-950/40 dark:via-gray-900/80 dark:to-danger-900/20 hover:shadow-lg hover:shadow-danger-500/15 hover:scale-[1.02] hover:ring-2 hover:ring-danger-400/50 hover:border-danger-300 dark:hover:border-danger-500';

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
            Stat::make('TOTAL DE CAPTACIÓN', (string) $total)
                ->icon('heroicon-m-user-plus')
                ->description(new HtmlString($descriptionHtml))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('planCorp')
                ->extraAttributes([
                    'class' => $cardTotal,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('CAPTACIÓN/PROSPECCIÓN', (string) $captacionProspeccion)
                ->icon('heroicon-m-clock')
                ->description('Aún en etapa inicial del embudo')
                ->color('gray')
                ->extraAttributes([
                    'class' => $cardCaptacion,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('CONTACTO INICIAL', (string) $contactoInicial)
                ->icon('heroicon-m-phone')
                ->description('Primer contacto comercial realizado')
                ->color('info')
                ->extraAttributes([
                    'class' => $cardContacto,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('EN PROCESO/EN NEGOCIACIÓN', (string) $enProcesoNegociacion)
                ->icon('heroicon-m-arrow-path')
                ->description('Prospectos en gestión o negociación')
                ->color('primary')
                ->extraAttributes([
                    'class' => $cardProceso,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('ALIADO ACTIVO', (string) $aliadoActivo)
                ->icon('heroicon-m-check-badge')
                ->description('Convertidos en aliados activos')
                ->color('success')
                ->extraAttributes([
                    'class' => $cardAliado,
                    'style' => 'min-height: 130px;',
                ]),
            Stat::make('INACTIVO', (string) $inactivo)
                ->icon('heroicon-m-x-circle')
                ->description('Prospectos marcados como inactivos')
                ->color('danger')
                ->extraAttributes([
                    'class' => $cardInactivo,
                    'style' => 'min-height: 130px;',
                ]),
        ];
    }
}
