<?php

namespace App\Filament\Master\Resources\Affiliations\Pages;

use App\Filament\Master\Resources\Affiliations\AffiliationResource;
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
