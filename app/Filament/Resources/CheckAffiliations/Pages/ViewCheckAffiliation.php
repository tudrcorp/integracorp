<?php

namespace App\Filament\Resources\CheckAffiliations\Pages;

use App\Filament\Resources\CheckAffiliations\CheckAffiliationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCheckAffiliation extends ViewRecord
{
    protected static string $resource = CheckAffiliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
