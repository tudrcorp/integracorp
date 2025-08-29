<?php

namespace App\Filament\Marketing\Resources\Events\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Marketing\Resources\Events\EventResource;
use App\Filament\Marketing\Resources\Events\Widgets\EventOverview;
use App\Filament\Marketing\Resources\Events\Widgets\ChartOverviewEvent;
use App\Filament\Marketing\Resources\Events\Widgets\ChartOverviewEventTwo;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EventOverview::class,
            ChartOverviewEvent::class,
            ChartOverviewEventTwo::class,
        ];
    }
    
}