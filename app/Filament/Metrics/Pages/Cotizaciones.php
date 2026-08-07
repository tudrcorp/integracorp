<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use App\Filament\Metrics\Widgets\CotizacionesByAgencyChart;
use App\Filament\Metrics\Widgets\CotizacionesByAgentChart;
use App\Filament\Metrics\Widgets\CotizacionesStatusMomStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Cotizaciones extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'COTIZACIONES';

    protected static ?string $navigationLabel = 'Cotizaciones';

    protected static ?string $title = 'Cotizaciones';

    protected static ?string $slug = 'cotizaciones';

    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.metrics.pages.cotizaciones';
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
            CotizacionesStatusMomStats::class,
            CotizacionesByAgentChart::class,
            CotizacionesByAgencyChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    public static function metricsModuleKey(): string
    {
        return 'cotizaciones';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Cotizaciones';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'Indicadores de cotizaciones, conversión y pipeline comercial.';
    }
}
