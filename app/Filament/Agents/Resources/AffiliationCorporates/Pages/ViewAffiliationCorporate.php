<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\Pages;

use App\Filament\Agents\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliationCorporate extends ViewRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Información general';

    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),
        ];
    }
}