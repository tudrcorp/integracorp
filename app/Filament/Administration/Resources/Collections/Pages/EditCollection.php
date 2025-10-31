<?php

namespace App\Filament\Administration\Resources\Collections\Pages;

use App\Filament\Administration\Resources\Collections\CollectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
