<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages\Negocios\Corretaje;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Clusters\Negocios\CorretajeCluster;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use App\Filament\Metrics\Widgets\CorretajeAgentsByActiveAffiliationAmountChart;
use App\Filament\Metrics\Widgets\CorretajeAgentsByActiveAffiliationsChart;
use App\Filament\Metrics\Widgets\CorretajeAgentsByStateChart;
use App\Filament\Metrics\Widgets\CorretajeAgentsRegistrationMomStats;
use App\Filament\Metrics\Widgets\CorretajeAgentsSalesByStateRadarChart;
use App\Filament\Metrics\Widgets\CorretajeAgentsStatsOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CorretajeAgents extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static ?string $cluster = CorretajeCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Agentes';

    protected static ?string $title = 'Corretaje · Agentes';

    protected static ?string $slug = 'agentes';

    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.metrics.pages.corretaje-agents';
    }

    /**
     * Widgets van en el footer para que el encabezado MÉTRICAS / KPI
     * del contenido de la página se renderice primero.
     *
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            CorretajeAgentsStatsOverview::class,
            CorretajeAgentsRegistrationMomStats::class,
            CorretajeAgentsByStateChart::class,
            CorretajeAgentsByActiveAffiliationsChart::class,
            CorretajeAgentsByActiveAffiliationAmountChart::class,
            CorretajeAgentsSalesByStateRadarChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    public static function metricsModuleKey(): string
    {
        return 'negocios.corretaje.agentes';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Agentes de corretaje';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'KPIs en tiempo real del canal de agentes de corretaje vía integracorp-api.';
    }
}
