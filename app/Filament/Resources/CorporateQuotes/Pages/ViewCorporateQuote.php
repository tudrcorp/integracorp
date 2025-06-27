<?php

namespace App\Filament\Resources\CorporateQuotes\Pages;

use App\Filament\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateQuote extends ViewRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
