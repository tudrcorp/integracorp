<?php

namespace App\Filament\Resources\BusinessLines\Pages;

use App\Filament\Resources\BusinessLines\BusinessLineResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBusinessLine extends EditRecord
{
    protected static string $resource = BusinessLineResource::class;

    protected static ?string $title = 'EDITAR';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}