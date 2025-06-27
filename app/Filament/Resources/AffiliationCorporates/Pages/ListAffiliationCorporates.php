<?php

namespace App\Filament\Resources\AffiliationCorporates\Pages;

use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'AFILIACION CORPORATIVA';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-m-rectangle-stack')
        ];
    }
}