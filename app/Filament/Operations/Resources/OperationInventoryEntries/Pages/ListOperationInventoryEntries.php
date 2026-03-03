<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Pages;

use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventoryEntries extends ListRecords
{
    protected static string $resource = OperationInventoryEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
