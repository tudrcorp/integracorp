<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Proveedores extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'PROVEEDORES';

    protected static ?string $navigationLabel = 'Proveedores';

    protected static ?string $title = 'Proveedores';

    protected static ?string $slug = 'proveedores';

    protected static ?int $navigationSort = 1;

    public static function metricsModuleKey(): string
    {
        return 'proveedores';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Proveedores';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'Desempeño, disponibilidad y clasificación de la red de proveedores.';
    }
}
