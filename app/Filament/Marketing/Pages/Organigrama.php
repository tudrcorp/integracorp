<?php

declare(strict_types=1);

namespace App\Filament\Marketing\Pages;

use App\Filament\Shared\OrgStructure\Pages\OrgChartPage;
use App\Filament\Shared\OrgStructure\Pages\ShowsPublishedOrgChartOnly;

class Organigrama extends OrgChartPage
{
    use ShowsPublishedOrgChartOnly;
}
