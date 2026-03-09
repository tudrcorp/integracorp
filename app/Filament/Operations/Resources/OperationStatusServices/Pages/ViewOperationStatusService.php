<?php

namespace App\Filament\Operations\Resources\OperationStatusServices\Pages;

use App\Filament\Operations\Resources\OperationStatusServices\OperationStatusServiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationStatusService extends ViewRecord
{
    protected static string $resource = OperationStatusServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
