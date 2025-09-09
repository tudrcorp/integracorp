<?php

namespace App\Filament\Marketing\Resources\InfoFrees\Pages;

use App\Filament\Marketing\Resources\InfoFrees\InfoFreeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInfoFree extends EditRecord
{
    protected static string $resource = InfoFreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
