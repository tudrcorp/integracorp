<?php

namespace App\Filament\Resources\TelemedicineStudiesLists\Pages;

use App\Filament\Resources\TelemedicineStudiesLists\TelemedicineStudiesListResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineStudiesList extends ViewRecord
{
    protected static string $resource = TelemedicineStudiesListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
