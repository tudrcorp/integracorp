<?php

namespace App\Filament\General\Resources\AffiliationCorporates\Pages;

use App\Filament\General\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afilizaciones Corporativas';

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         CreateAction::make()
    //             ->label('Crear')
    //             ->icon('heroicon-s-user-group'),
    //     ];
    // }
}