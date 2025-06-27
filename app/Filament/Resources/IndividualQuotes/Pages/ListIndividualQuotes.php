<?php

namespace App\Filament\Resources\IndividualQuotes\Pages;

use App\Filament\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndividualQuotes extends ListRecords
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'GESTION DE COTIZACIONES INDIVIDUALES';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-m-tag')
        ];
    }
}