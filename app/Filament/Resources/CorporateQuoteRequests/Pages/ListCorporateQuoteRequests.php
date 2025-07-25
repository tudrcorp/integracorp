<?php

namespace App\Filament\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuoteRequests extends ListRecords
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Solicitud de Cotización Corporativa';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear solicitud')
                ->icon('heroicon-m-plus')
        ];
    }
}