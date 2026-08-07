<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages\Negocios\Corretaje;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Clusters\Negocios\CorretajeCluster;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use App\Filament\Metrics\Widgets\CorretajeAgenciesByActiveAffiliationAmountChart;
use App\Filament\Metrics\Widgets\CorretajeAgenciesByActiveAffiliationsChart;
use App\Filament\Metrics\Widgets\CorretajeAgenciesByActiveCorporateAffiliationsChart;
use App\Filament\Metrics\Widgets\CorretajeAgenciesByStateChart;
use App\Filament\Metrics\Widgets\CorretajeAgenciesRegistrationMomStats;
use App\Filament\Metrics\Widgets\CorretajeAgenciesStatsOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CorretajeAgencies extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static ?string $cluster = CorretajeCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Agencias';

    protected static ?string $title = 'Corretaje · Agencias';

    protected static ?string $slug = 'agencias';

    protected static ?int $navigationSort = 2;

    public function getView(): string
    {
        return 'filament.metrics.pages.corretaje-agencies';
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
            CorretajeAgenciesStatsOverview::class,
            CorretajeAgenciesRegistrationMomStats::class,
            CorretajeAgenciesByStateChart::class,
            CorretajeAgenciesByActiveAffiliationsChart::class,
            CorretajeAgenciesByActiveCorporateAffiliationsChart::class,
            CorretajeAgenciesByActiveAffiliationAmountChart::class,
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
        return 'negocios.corretaje.agencias';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Agencias de corretaje';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'KPIs en tiempo real del canal de agencias de corretaje vía integracorp-api.';
    }
}
