<?php

namespace App\Filament\Agents\Resources\Affiliations\Pages;

use App\Filament\Agents\Resources\Affiliations\AffiliationResource;
use App\Filament\Shared\CommercialTelemedicine\Actions\ViewAffiliationTelemedicinePatientsAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliation extends ViewRecord
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Información General';

    protected function getHeaderActions(): array
    {
        return [
            ViewAffiliationTelemedicinePatientsAction::forRecord($this->getRecord()),
        ];
    }
}
