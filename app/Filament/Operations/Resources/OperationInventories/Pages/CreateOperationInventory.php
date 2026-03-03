<?php

namespace App\Filament\Operations\Resources\OperationInventories\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationInventory extends CreateRecord
{
    protected static string $resource = OperationInventoryResource::class;
}
