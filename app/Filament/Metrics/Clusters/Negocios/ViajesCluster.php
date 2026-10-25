<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Clusters\Negocios;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ViajesCluster extends Cluster
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Viajes';

    protected static string|UnitEnum|null $navigationGroup = 'NEGOCIOS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAmericas;

    protected static ?int $navigationSort = 2;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $clusterBreadcrumb = 'Viajes';

    /**
     * Página antigua (integracorp-api): oculta del menú mientras se valida el
     * panel corporativo, que lee la base de datos. Sigue abriendo por URL.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
