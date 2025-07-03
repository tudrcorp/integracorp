<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Pages;

use App\Filament\Master\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuotes extends ListRecords
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Cotizaciones corporativas';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}