<?php

namespace App\Filament\Resources\AffiliationCorporates\Pages;

use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliationCorporate extends EditRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'DETALLE DE AFILIACION';

    protected function getHeaderActions(): array
    {
        return [
            // ViewAction::make(),
            // DeleteAction::make(),
        ];
    }
}