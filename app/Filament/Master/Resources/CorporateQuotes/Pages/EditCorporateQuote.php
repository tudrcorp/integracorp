<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Pages;

use App\Filament\Master\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCorporateQuote extends EditRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
