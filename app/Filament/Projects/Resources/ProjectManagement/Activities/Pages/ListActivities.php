<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
