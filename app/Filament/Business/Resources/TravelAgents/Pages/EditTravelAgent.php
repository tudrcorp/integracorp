<?php

namespace App\Filament\Business\Resources\TravelAgents\Pages;

use App\Filament\Business\Resources\TravelAgents\TravelAgentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTravelAgent extends EditRecord
{
    protected static string $resource = TravelAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
