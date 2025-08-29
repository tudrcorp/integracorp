<?php

namespace App\Filament\Resources\CheckAgentAgencies\Pages;

use App\Filament\Resources\CheckAgentAgencies\CheckAgentAgencyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCheckAgentAgency extends ViewRecord
{
    protected static string $resource = CheckAgentAgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
