<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Pages;

use App\Filament\Master\Resources\CorporateQuotes\CorporateQuoteResource;
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
