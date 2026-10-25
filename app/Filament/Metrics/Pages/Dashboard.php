<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Metrics\Pages\Concerns\IsCorporateMetricsPage;
use App\Filament\Metrics\Widgets\Corporate\CorporateAreaPulse;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\CorporateInsightsWidget;
use App\Filament\Metrics\Widgets\Corporate\CorporateVenezuelaMap;
use App\Filament\Metrics\Widgets\Corporate\ExecutiveKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\IncomeTrendChart;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Resumen ejecutivo: la empresa completa en una pantalla. Una cifra clave por
 * área, la lectura automática del período, el mapa y el pulso de cada área.
 * Todo sale de la base de datos (ya no de integracorp-api).
 */
class Dashboard extends BaseDashboard
{
    use IsCorporateMetricsPage;

    protected static ?string $navigationLabel = 'Resumen ejecutivo';

    protected static ?string $title = 'Resumen ejecutivo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?int $navigationSort = 1;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ExecutiveKpiTiles::class,
            IncomeTrendChart::class,
            CorporateInsightsWidget::class,
            CorporateVenezuelaMap::class,
            CorporateAreaPulse::class,
            CorporateDetailWidget::class,
        ];
    }
}
