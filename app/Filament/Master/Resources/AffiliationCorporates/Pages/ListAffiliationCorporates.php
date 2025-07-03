<?php

namespace App\Filament\Master\Resources\AffiliationCorporates\Pages;

use App\Filament\Master\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones corporativas';

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         CreateAction::make()
    //             ->label('Crear')
    //             ->icon('heroicon-s-user-group'),
    //     ];
    // }
}