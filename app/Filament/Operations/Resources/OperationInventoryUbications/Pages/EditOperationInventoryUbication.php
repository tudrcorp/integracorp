<?php

namespace App\Filament\Operations\Resources\OperationInventoryUbications\Pages;

use App\Filament\Operations\Resources\OperationInventoryUbications\OperationInventoryUbicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationInventoryUbication extends EditRecord
{
    protected static string $resource = OperationInventoryUbicationResource::class;

    protected static ?string $title = 'Editar Almacén';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver'),
            DeleteAction::make()->label('Eliminar'),
        ];
    }
}
