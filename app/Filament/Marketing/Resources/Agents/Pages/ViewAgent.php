<?php

namespace App\Filament\Marketing\Resources\Agents\Pages;

use App\Filament\Marketing\Resources\Agents\AgentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgent extends ViewRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
