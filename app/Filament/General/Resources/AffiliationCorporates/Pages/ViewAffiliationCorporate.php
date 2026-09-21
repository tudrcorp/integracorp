<?php

namespace App\Filament\General\Resources\AffiliationCorporates\Pages;

use App\Filament\General\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Shared\CommercialTelemedicine\Actions\ViewAffiliationTelemedicinePatientsAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliationCorporate extends ViewRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAffiliationTelemedicinePatientsAction::forRecord($this->getRecord()),
            EditAction::make(),
        ];
    }
}
