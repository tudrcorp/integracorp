<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Groups\GroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
