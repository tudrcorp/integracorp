<?php

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliation extends ViewRecord
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Detalles de la Afiliación';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}