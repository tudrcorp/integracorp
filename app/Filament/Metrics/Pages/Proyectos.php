<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Concerns\HasMetricsLiquidGlassPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Proyectos extends Page
{
    use AuthorizesDepartmentNavigation;
    use HasMetricsLiquidGlassPage;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'PROYECTOS';

    protected static ?string $navigationLabel = 'Proyectos';

    protected static ?string $title = 'Proyectos';

    protected static ?string $slug = 'proyectos';

    protected static ?int $navigationSort = 1;

    public static function metricsModuleKey(): string
    {
        return 'proyectos';
    }

    public static function metricsModuleTitle(): string
    {
        return 'Proyectos';
    }

    public static function metricsModuleSubtitle(): string
    {
        return 'Avance, burndown, carga de equipos y salud del portafolio.';
    }
}
