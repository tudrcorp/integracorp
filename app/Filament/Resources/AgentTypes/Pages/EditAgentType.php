<?php

namespace App\Filament\Resources\AgentTypes\Pages;

use App\Filament\Resources\AgentTypes\AgentTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAgentType extends EditRecord
{
    protected static string $resource = AgentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
