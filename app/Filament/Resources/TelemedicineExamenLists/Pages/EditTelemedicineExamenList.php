<?php

namespace App\Filament\Resources\TelemedicineExamenLists\Pages;

use App\Filament\Resources\TelemedicineExamenLists\TelemedicineExamenListResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineExamenList extends EditRecord
{
    protected static string $resource = TelemedicineExamenListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
