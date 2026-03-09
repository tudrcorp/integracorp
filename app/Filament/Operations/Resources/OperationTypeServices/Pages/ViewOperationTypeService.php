<?php

namespace App\Filament\Operations\Resources\OperationTypeServices\Pages;

use App\Filament\Operations\Resources\OperationTypeServices\OperationTypeServiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationTypeService extends ViewRecord
{
    protected static string $resource = OperationTypeServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
