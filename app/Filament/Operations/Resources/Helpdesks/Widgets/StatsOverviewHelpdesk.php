<?php

namespace App\Filament\Operations\Resources\Helpdesks\Widgets;

use App\Filament\Business\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk as BaseStatsOverviewHelpdesk;
use App\Filament\Operations\Resources\Helpdesks\Pages\ListHelpdesks;

class StatsOverviewHelpdesk extends BaseStatsOverviewHelpdesk
{
    protected function getTablePage(): string
    {
        return ListHelpdesks::class;
    }
}
