<?php

namespace App\Filament\Marketing\Resources\TravelAgencies\Pages;

use App\Filament\Marketing\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTravelAgency extends ViewRecord
{
    protected static string $resource = TravelAgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),
        ];
    }
}
