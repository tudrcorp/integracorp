<?php

namespace App\Filament\Administration\Resources\Helpdesks\Widgets;

use App\Filament\Administration\Resources\Helpdesks\Pages\ListHelpdesks;
use App\Filament\Business\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart as BaseHelpdeskStatusWeeklyChart;

class HelpdeskStatusWeeklyChart extends BaseHelpdeskStatusWeeklyChart
{
    protected function getTablePage(): string
    {
        return ListHelpdesks::class;
    }
}
