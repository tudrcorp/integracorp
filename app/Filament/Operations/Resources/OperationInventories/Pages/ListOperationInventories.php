<?php

namespace App\Filament\Operations\Resources\OperationInventories\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventories extends ListRecords
{
    protected static string $resource = OperationInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
