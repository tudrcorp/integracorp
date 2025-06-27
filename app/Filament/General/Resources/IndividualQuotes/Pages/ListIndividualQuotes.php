<?php

namespace App\Filament\General\Resources\IndividualQuotes\Pages;

use App\Filament\General\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndividualQuotes extends ListRecords
{
    protected static string $resource = IndividualQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
