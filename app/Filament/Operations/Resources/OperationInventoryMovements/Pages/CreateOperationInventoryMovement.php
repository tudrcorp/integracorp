<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Pages;

use App\Filament\Operations\Resources\OperationInventoryMovements\OperationInventoryMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationInventoryMovement extends CreateRecord
{
    protected static string $resource = OperationInventoryMovementResource::class;
}
