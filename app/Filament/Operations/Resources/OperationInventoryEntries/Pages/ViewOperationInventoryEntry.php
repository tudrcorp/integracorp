<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Pages;

use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationInventoryEntry extends ViewRecord
{
    protected static string $resource = OperationInventoryEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
