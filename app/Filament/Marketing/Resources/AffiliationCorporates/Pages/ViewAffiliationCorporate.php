<?php

namespace App\Filament\Marketing\Resources\AffiliationCorporates\Pages;

use App\Filament\Marketing\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliationCorporate extends ViewRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
