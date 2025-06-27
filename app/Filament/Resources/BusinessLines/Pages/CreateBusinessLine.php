<?php

namespace App\Filament\Resources\BusinessLines\Pages;

use App\Filament\Resources\BusinessLines\BusinessLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessLine extends CreateRecord
{
    protected static string $resource = BusinessLineResource::class;

    protected static ?string $title = 'CREAR LÍNEA DE SERVICIO';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}