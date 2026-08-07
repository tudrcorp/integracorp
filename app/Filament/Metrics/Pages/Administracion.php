<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use App\Filament\Metrics\Widgets\AdministracionSalesMomStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Administracion extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACION';

    protected static ?string $navigationLabel = 'Administración';

    protected static ?string $title = 'Administración';

    protected static ?string $slug = 'administracion';

    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.metrics.pages.administracion';
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
            AdministracionSalesMomStats::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    public static function metricsModuleKey(): string
    {
        return 'administracion';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Administración';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'KPIs financieros, cobranza, comisiones y control administrativo.';
    }
}
