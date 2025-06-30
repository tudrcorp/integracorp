<?php

namespace App\Filament\Master\Resources\IndividualQuotes\Pages;

use App\Filament\Master\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndividualQuotes extends ListRecords
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Cotizaciones individuales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cotización individual')
                ->icon('heroicon-s-plus')
        ];
    }
}