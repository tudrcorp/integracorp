<?php

namespace App\Filament\Resources\Agents\Pages;

use App\Filament\Resources\Agents\AgentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'EDITAR AGENTE';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}