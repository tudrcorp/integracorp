<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages\Negocios\Viajes;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Clusters\Negocios\ViajesCluster;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ViajesAgencies extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static ?string $cluster = ViajesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Agencias';

    protected static ?string $title = 'Viajes · Agencias';

    protected static ?string $slug = 'agencias';

    protected static ?int $navigationSort = 1;

    public static function metricsModuleKey(): string
    {
        return 'negocios.viajes.agencias';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Agencias de viajes';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'KPIs, stats y tendencias del canal de agencias de viajes.';
    }
}
