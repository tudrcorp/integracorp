<?php

namespace App\Filament\Resources\Limits\Pages;

use App\Filament\Resources\Limits\LimitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLimit extends CreateRecord
{
    protected static string $resource = LimitResource::class;

    protected static ?string $title = 'Crear Limite';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}