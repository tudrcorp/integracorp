<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliationCorporate extends EditRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Editar Affiliation Corporativa';

    protected function getFormActions(): array
    {
        return [
            
        ];
    }
    
}
