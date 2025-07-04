<?php

namespace App\Filament\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateQuoteRequest extends ViewRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'INFORMACION GENERAL';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}