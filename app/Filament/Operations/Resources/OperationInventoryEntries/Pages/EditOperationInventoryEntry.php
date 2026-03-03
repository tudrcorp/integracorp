<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Pages;

use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationInventoryEntry extends EditRecord
{
    protected static string $resource = OperationInventoryEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
