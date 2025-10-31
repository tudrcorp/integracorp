<?php

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliation extends EditRecord
{
    protected static string $resource = AffiliationResource::class;

    protected function getFormActions(): array
    {
        return [
            
        ];
    }
    

}
