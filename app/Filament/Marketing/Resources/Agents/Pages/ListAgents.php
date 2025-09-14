<?php

namespace App\Filament\Marketing\Resources\Agents\Pages;

use App\Filament\Marketing\Resources\Agents\AgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}