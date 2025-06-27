<?php

namespace App\Filament\Resources\AgencyTypes\Pages;

use App\Filament\Resources\AgencyTypes\AgencyTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAgencyType extends EditRecord
{
    protected static string $resource = AgencyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
