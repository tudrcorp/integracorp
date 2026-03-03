<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Pages;

use App\Filament\Business\Resources\DressTylorQuotes\DressTylorQuoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDressTylorQuote extends ViewRecord
{
    protected static string $resource = DressTylorQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
