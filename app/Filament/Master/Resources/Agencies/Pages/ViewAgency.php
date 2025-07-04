<?php

namespace App\Filament\Master\Resources\Agencies\Pages;

use App\Filament\Master\Resources\Agencies\AgencyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Información General';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}