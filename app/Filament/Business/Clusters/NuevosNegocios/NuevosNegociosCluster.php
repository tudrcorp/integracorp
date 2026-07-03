<?php

declare(strict_types=1);

namespace App\Filament\Business\Clusters\NuevosNegocios;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class NuevosNegociosCluster extends Cluster
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Nuevos Negocios';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 5;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
