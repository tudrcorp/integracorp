<?php

namespace App\Filament\General\Resources\CorporateQuotes\Pages;

use App\Filament\General\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuotes extends ListRecords
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Cotizaciones Corporativas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cotización corporativa')
                ->icon('heroicon-s-plus')
        ];
    }
}