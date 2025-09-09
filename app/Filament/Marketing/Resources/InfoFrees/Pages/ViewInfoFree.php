<?php

namespace App\Filament\Marketing\Resources\InfoFrees\Pages;

use App\Filament\Marketing\Resources\InfoFrees\InfoFreeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInfoFree extends ViewRecord
{
    protected static string $resource = InfoFreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
