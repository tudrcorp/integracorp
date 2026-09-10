<?php

namespace App\Filament\Master\Resources\AffiliationCorporates\Pages;

use App\Filament\Master\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Shared\CommercialTelemedicine\Actions\ViewAffiliationTelemedicinePatientsAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliationCorporate extends ViewRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAffiliationTelemedicinePatientsAction::forRecord($this->getRecord()),
        ];
    }
}
