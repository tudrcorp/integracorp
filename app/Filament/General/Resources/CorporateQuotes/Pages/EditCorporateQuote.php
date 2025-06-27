<?php

namespace App\Filament\General\Resources\CorporateQuotes\Pages;

use App\Filament\General\Resources\CorporateQuotes\CorporateQuoteResource;
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
