<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use App\Filament\Metrics\Widgets\AfiliacionesCorporativasByMonthChart;
use App\Filament\Metrics\Widgets\AfiliacionesIndividualesByMonthChart;
use App\Filament\Metrics\Widgets\AfiliacionesPlanAmountCombinedPieChart;
use App\Filament\Metrics\Widgets\AfiliacionesPlansDemandChart;
use App\Filament\Metrics\Widgets\AfiliacionesStatusByStatePieChart;
use App\Filament\Metrics\Widgets\AfiliacionesStatusMomStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Afiliaciones extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?string $navigationLabel = 'Afiliaciones';

    protected static ?string $title = 'Afiliaciones';

    protected static ?string $slug = 'afiliaciones';

    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.metrics.pages.afiliaciones';
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
            AfiliacionesStatusMomStats::class,
            AfiliacionesStatusByStatePieChart::class,
            AfiliacionesPlansDemandChart::class,
            AfiliacionesPlanAmountCombinedPieChart::class,
            AfiliacionesIndividualesByMonthChart::class,
            AfiliacionesCorporativasByMonthChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 2,
        ];
    }

    public static function metricsModuleKey(): string
    {
        return 'afiliaciones';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Afiliaciones';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'Cuántos afiliados individuales y corporativos se registran cada mes, y cómo va el año.';
    }
}
