<?php

namespace App\Filament\Resources\TelemedicineExamenLists\Pages;

use App\Filament\Resources\TelemedicineExamenLists\TelemedicineExamenListResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineExamenList extends ViewRecord
{
    protected static string $resource = TelemedicineExamenListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
