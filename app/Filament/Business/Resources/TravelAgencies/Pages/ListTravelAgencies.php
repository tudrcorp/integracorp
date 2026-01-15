<?php

namespace App\Filament\Business\Resources\TravelAgencies\Pages;

use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelAgencies extends ListRecords
{
    protected static string $resource = TravelAgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
