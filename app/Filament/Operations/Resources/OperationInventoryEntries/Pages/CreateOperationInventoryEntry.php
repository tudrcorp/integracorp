<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Pages;

use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationInventoryEntry extends CreateRecord
{
    protected static string $resource = OperationInventoryEntryResource::class;
}
