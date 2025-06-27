<?php

namespace App\Filament\Resources\Limits\Pages;

use App\Filament\Resources\Limits\LimitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLimit extends EditRecord
{
    protected static string $resource = LimitResource::class;

    protected static ?string $title = 'Editar Límite';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}