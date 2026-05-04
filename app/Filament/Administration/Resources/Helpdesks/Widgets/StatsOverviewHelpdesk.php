<?php

namespace App\Filament\Administration\Resources\Helpdesks\Widgets;

use App\Filament\Administration\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Filament\Business\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk as BaseStatsOverviewHelpdesk;

class StatsOverviewHelpdesk extends BaseStatsOverviewHelpdesk
{
    protected function getTablePage(): string
    {
        return ListHelpdesks::class;
    }
}
