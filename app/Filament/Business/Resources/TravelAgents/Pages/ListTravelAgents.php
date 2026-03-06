<?php

namespace App\Filament\Business\Resources\TravelAgents\Pages;

use App\Filament\Business\Resources\TravelAgents\TravelAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelAgents extends ListRecords
{
    protected static string $resource = TravelAgentResource::class;

    protected static ?string $title = 'Agentes De Viaje';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make()
            //     ->label('Crear Agente De Viaje')
            //     ->color('primary')
            //     ->icon('heroicon-o-plus'),
        ];
    }
}
