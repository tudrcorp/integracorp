<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Operaciones extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'OPERACIONES';

    protected static ?string $navigationLabel = 'Operaciones';

    protected static ?string $title = 'Operaciones';

    protected static ?string $slug = 'operaciones';

    protected static ?int $navigationSort = 1;

    public static function metricsModuleKey(): string
    {
        return 'operaciones';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Operaciones';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'Servicios, inventario, telemedicina y coordinación operativa.';
    }
}
