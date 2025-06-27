<?php

namespace App\Filament\Master\Resources\IndividualQuotes\Pages;

use App\Filament\Master\Resources\IndividualQuotes\IndividualQuoteResource;
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
