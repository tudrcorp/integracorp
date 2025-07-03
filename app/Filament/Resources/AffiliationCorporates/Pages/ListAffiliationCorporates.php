<?php

namespace App\Filament\Resources\AffiliationCorporates\Pages;

use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones Corporativas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva afiliación')
                ->icon('heroicon-m-plus'),
        ];
    }
}