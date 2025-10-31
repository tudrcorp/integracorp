<?php

namespace App\Filament\Administration\Resources\Agents\Pages;

use App\Filament\Administration\Resources\Agents\AgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Listado de Agentes';  

}
