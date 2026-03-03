<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Pages;

use App\Filament\Operations\Resources\OperationInventoryMovements\OperationInventoryMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventoryMovements extends ListRecords
{
    protected static string $resource = OperationInventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
