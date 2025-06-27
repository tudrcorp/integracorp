<?php

namespace App\Filament\Resources\TypeServices\Pages;

use App\Filament\Resources\TypeServices\TypeServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTypeService extends EditRecord
{
    protected static string $resource = TypeServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
