<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\Pages;

use App\Filament\Agents\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIndividualQuote extends ViewRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Información General';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

}