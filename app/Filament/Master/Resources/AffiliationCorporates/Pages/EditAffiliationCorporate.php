<?php

namespace App\Filament\Master\Resources\AffiliationCorporates\Pages;

use App\Filament\Master\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliationCorporate extends EditRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
