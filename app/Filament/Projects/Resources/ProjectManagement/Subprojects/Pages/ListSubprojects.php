<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubprojects extends ListRecords
{
    protected static string $resource = SubprojectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
