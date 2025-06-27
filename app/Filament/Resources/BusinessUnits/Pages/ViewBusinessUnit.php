<?php

namespace App\Filament\Resources\BusinessUnits\Pages;

use App\Filament\Resources\BusinessUnits\BusinessUnitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessUnit extends ViewRecord
{
    protected static string $resource = BusinessUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
