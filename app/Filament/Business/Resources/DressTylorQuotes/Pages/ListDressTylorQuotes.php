<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Pages;

use App\Filament\Business\Resources\DressTylorQuotes\DressTylorQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDressTylorQuotes extends ListRecords
{
    protected static string $resource = DressTylorQuoteResource::class;

    protected static ?string $title = 'Cotizador Dress Tylor';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Cotización')
                ->icon('heroicon-o-document-text'),
        ];
    }
}
