<?php

namespace App\Filament\General\Resources\CorporateQuoteRequests\Pages;

use App\Filament\General\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuoteRequests extends ListRecords
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'SOLICITUDES DE COTIZACIÓN CORPORATIVA';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('NUEVA SOLICITUD')
                ->icon('heroicon-s-plus'),
        ];
    }
}