<?php

namespace App\Filament\Master\Resources\Agents\Pages;

use App\Filament\Master\Resources\Agents\AgentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Formulario de Agente';
}