<?php

namespace App\Filament\Operations\Resources\OperationInventories\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationInventory extends EditRecord
{
    protected static string $resource = OperationInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
