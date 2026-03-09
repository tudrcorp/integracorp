<?php

namespace App\Filament\Operations\Resources\OperationStatusServices\Pages;

use App\Filament\Operations\Resources\OperationStatusServices\OperationStatusServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationStatusServices extends ListRecords
{
    protected static string $resource = OperationStatusServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
