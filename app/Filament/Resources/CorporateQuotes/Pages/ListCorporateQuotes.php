<?php

namespace App\Filament\Resources\CorporateQuotes\Pages;

use App\Filament\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\Action;
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
                ->label('Crear')
                ->icon('heroicon-c-wallet'),    
        ];
    }
}