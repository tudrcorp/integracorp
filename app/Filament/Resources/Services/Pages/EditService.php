<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected static ?string $title = 'Editar Servicio';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}