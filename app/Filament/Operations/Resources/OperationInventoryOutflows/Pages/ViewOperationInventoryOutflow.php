<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Pages;

use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationInventoryOutflow extends ViewRecord
{
    protected static string $resource = OperationInventoryOutflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
