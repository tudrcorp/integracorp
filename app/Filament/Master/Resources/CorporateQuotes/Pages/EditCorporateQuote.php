<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Pages;

use App\Filament\Master\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCorporateQuote extends EditRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Pre-Afiliación Multi Plan(es)';

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}