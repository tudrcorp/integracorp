<?php

namespace App\Filament\Business\Resources\TravelAgents\Pages;

use App\Filament\Business\Resources\TravelAgents\TravelAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelAgents extends ListRecords
{
    protected static string $resource = TravelAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
