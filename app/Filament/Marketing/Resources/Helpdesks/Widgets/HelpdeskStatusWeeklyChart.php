<?php

namespace App\Filament\Marketing\Resources\Helpdesks\Widgets;

use App\Filament\Business\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart as BaseHelpdeskStatusWeeklyChart;
use App\Filament\Marketing\Resources\Helpdesks\Pages\ListHelpdesks;

class HelpdeskStatusWeeklyChart extends BaseHelpdeskStatusWeeklyChart
{
    protected function getTablePage(): string
    {
        return ListHelpdesks::class;
    }
}
