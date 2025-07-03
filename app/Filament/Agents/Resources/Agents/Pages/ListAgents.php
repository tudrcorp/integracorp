<?php

namespace App\Filament\Agents\Resources\Agents\Pages;

use App\Filament\Agents\Resources\Agents\AgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Subagentes Asignados';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}