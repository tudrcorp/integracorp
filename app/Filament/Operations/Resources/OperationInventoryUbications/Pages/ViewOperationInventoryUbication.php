<?php

namespace App\Filament\Operations\Resources\OperationInventoryUbications\Pages;

use App\Filament\Operations\Resources\OperationInventoryUbications\OperationInventoryUbicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationInventoryUbication extends ViewRecord
{
    protected static string $resource = OperationInventoryUbicationResource::class;

    protected static ?string $title = 'Ver Almacén';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar'),
        ];
    }
}
