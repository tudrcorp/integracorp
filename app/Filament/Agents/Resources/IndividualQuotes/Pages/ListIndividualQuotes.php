<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\Pages;

use App\Filament\Agents\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndividualQuotes extends ListRecords
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'COTIZACIONES INDIVIDULAES';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-m-tag')
        ];
    }
}