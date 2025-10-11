<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Business\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuoteRequests extends ListRecords
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Solicitudes Dress Taylor';

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear solicitud')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

}