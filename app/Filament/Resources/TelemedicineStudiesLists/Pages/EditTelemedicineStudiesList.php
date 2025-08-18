<?php

namespace App\Filament\Resources\TelemedicineStudiesLists\Pages;

use App\Filament\Resources\TelemedicineStudiesLists\TelemedicineStudiesListResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineStudiesList extends EditRecord
{
    protected static string $resource = TelemedicineStudiesListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
