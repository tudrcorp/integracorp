<?php

namespace App\Filament\Administration\Resources\Agents\Pages;

use App\Filament\Administration\Resources\Agents\AgentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Editar Informacio del Agente';  

}
