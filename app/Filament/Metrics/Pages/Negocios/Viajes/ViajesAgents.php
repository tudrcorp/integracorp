<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages\Negocios\Viajes;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Clusters\Negocios\ViajesCluster;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ViajesAgents extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static ?string $cluster = ViajesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Agentes';

    protected static ?string $title = 'Viajes · Agentes';

    protected static ?string $slug = 'agentes';

    protected static ?int $navigationSort = 2;

    public static function metricsModuleKey(): string
    {
        return 'negocios.viajes.agentes';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Agentes de viajes';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'KPIs, stats y tendencias del canal de agentes de viajes.';
    }
}
