<?php

namespace App\Filament\Marketing\Resources\Events\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Marketing\Resources\Events\EventResource;
use App\Filament\Marketing\Resources\Events\Widgets\EventOverview;
use App\Filament\Marketing\Resources\Events\Widgets\StatsOverviewEvent;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    
    
    
}