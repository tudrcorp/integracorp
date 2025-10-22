<?php

namespace App\Filament\Master\Resources\Agents\Pages;

use App\Filament\Master\Resources\Agents\AgentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Perfil de Agente';
}