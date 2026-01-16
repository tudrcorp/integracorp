<?php

namespace App\Filament\Business\Resources\TravelAgencies\Pages;

use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelAgencies extends ListRecords
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = "Listado de Agencias de Viajes";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
