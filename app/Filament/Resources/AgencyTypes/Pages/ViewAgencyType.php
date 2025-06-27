<?php

namespace App\Filament\Resources\AgencyTypes\Pages;

use App\Filament\Resources\AgencyTypes\AgencyTypeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgencyType extends ViewRecord
{
    protected static string $resource = AgencyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
