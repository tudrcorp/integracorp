<?php

namespace App\Filament\Master\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Master\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuoteRequests extends ListRecords
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Splicitudes de cotizaciones corporativas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva solicitud')
                ->icon('heroicon-s-plus'),
        ];
    }
}