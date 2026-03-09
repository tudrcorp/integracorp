<?php

namespace App\Filament\Operations\Resources\OperationTypeServices\Pages;

use App\Filament\Operations\Resources\OperationTypeServices\OperationTypeServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOperationTypeService extends CreateRecord
{
    protected static string $resource = OperationTypeServiceResource::class;

    protected static ?string $title = 'Crear Tipo de Servicio';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = Auth::user()->id;
        $data['created_by'] = Auth::user()->id;

        return $data;
    }
}
