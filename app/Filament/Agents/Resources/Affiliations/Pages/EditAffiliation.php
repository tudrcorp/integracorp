<?php

namespace App\Filament\Agents\Resources\Affiliations\Pages;

use App\Filament\Agents\Resources\Affiliations\AffiliationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliation extends EditRecord
{
    protected static string $resource = AffiliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
