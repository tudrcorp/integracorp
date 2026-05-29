<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Groups\GroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
