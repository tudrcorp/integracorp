<?php

namespace App\Filament\Marketing\Resources\TravelAgencies\Pages;

use App\Filament\Marketing\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelAgencies extends ListRecords
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = 'Lista de Agencias de Viajes';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
