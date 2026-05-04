<?php

namespace App\Filament\Marketing\Resources\Helpdesks\Widgets;

use App\Filament\Business\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk as BaseStatsOverviewHelpdesk;
use App\Filament\Marketing\Resources\Helpdesks\Pages\ListHelpdesks;

class StatsOverviewHelpdesk extends BaseStatsOverviewHelpdesk
{
    protected function getTablePage(): string
    {
        return ListHelpdesks::class;
    }
}
