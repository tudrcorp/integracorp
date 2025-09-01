<?php

namespace App\Filament\Master\Resources\CorporateQuoteRequests\Pages;

use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Master\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class ListCorporateQuoteRequests extends ListRecords
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Solicitudes Dress Taylor';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear solicitud')
                ->icon(Heroicon::Plus),
        ];
    }
}