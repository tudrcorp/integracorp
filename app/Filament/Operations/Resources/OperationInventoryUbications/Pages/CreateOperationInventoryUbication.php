<?php

namespace App\Filament\Operations\Resources\OperationInventoryUbications\Pages;

use App\Filament\Operations\Resources\OperationInventoryUbications\OperationInventoryUbicationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOperationInventoryUbication extends CreateRecord
{
    protected static string $resource = OperationInventoryUbicationResource::class;

    protected static ?string $title = 'Crear Almacén';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()?->name;

        return $data;
    }
}
