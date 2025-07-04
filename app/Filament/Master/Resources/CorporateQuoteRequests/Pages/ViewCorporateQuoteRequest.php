<?php

namespace App\Filament\Master\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Master\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateQuoteRequest extends ViewRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
