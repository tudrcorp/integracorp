<?php

namespace App\Filament\Marketing\Resources\Agents\Pages;

use App\Filament\Marketing\Resources\Agents\AgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Lista de Agentes de Corretaje';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}