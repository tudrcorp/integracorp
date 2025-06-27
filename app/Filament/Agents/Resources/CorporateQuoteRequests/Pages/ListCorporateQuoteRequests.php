<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuoteRequests extends ListRecords
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Solicitudes de cotización corporativa';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crea solicitud')
                ->icon('heroicon-s-plus'),
        ];
    }
}