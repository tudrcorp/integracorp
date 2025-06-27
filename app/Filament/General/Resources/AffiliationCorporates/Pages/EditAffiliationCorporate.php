<?php

namespace App\Filament\General\Resources\AffiliationCorporates\Pages;

use App\Filament\General\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliationCorporate extends EditRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
