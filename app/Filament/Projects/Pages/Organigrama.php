<?php

declare(strict_types=1);

namespace App\Filament\Projects\Pages;

use App\Filament\Shared\OrgStructure\Pages\OrgChartPage;
use App\Filament\Shared\OrgStructure\Pages\ShowsPublishedOrgChartOnly;
use UnitEnum;

class Organigrama extends OrgChartPage
{
    use ShowsPublishedOrgChartOnly;

    /** En este panel no existe el grupo «DEL USUARIO»: va suelto en el menú. */
    protected static string|UnitEnum|null $navigationGroup = null;
}
