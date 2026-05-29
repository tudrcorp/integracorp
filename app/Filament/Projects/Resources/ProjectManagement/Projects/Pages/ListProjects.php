<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
