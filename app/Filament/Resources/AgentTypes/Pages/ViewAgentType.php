<?php

namespace App\Filament\Resources\AgentTypes\Pages;

use App\Filament\Resources\AgentTypes\AgentTypeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgentType extends ViewRecord
{
    protected static string $resource = AgentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
