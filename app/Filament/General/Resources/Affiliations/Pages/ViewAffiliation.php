<?php

namespace App\Filament\General\Resources\Affiliations\Pages;

use App\Filament\General\Resources\Affiliations\AffiliationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliation extends ViewRecord
{
    protected static string $resource = AffiliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
