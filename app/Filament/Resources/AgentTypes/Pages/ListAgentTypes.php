<?php

namespace App\Filament\Resources\AgentTypes\Pages;

use App\Filament\Resources\AgentTypes\AgentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgentTypes extends ListRecords
{
    protected static string $resource = AgentTypeResource::class;

    protected static ?string $title = 'GESTION TIPOS DE AGENCIAS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-s-user-group')
        ];
    }
}