<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Pages;

use App\Filament\Operations\Resources\OperationInventoryMovements\OperationInventoryMovementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationInventoryMovement extends ViewRecord
{
    protected static string $resource = OperationInventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
