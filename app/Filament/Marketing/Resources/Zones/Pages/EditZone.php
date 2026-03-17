<?php

namespace App\Filament\Marketing\Resources\Zones\Pages;

use App\Filament\Marketing\Resources\Zones\ZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZone extends EditRecord
{
    protected static string $resource = ZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
