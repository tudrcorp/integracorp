<?php

namespace App\Filament\Resources\TypeServices\Pages;

use App\Filament\Resources\TypeServices\TypeServiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTypeService extends ViewRecord
{
    protected static string $resource = TypeServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
