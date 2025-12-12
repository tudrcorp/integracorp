<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Pages;

use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCorporateQuote extends EditRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Detalles de Cotización y Gestión de Población';

    protected function getHeaderActions(): array
    {
        return [
            // ViewAction::make(),
            // DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}