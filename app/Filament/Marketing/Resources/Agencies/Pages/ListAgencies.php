<?php

namespace App\Filament\Marketing\Resources\Agencies\Pages;

use App\Filament\Marketing\Resources\Agencies\AgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}