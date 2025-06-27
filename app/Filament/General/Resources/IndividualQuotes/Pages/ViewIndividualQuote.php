<?php

namespace App\Filament\General\Resources\IndividualQuotes\Pages;

use App\Filament\General\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIndividualQuote extends ViewRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
