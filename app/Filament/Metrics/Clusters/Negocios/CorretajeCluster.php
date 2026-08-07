<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Clusters\Negocios;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CorretajeCluster extends Cluster
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Corretaje';

    protected static string|UnitEnum|null $navigationGroup = 'NEGOCIOS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $clusterBreadcrumb = 'Corretaje';
}
