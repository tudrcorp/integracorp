<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\Pages;

use App\Filament\Agents\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones corporativas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}