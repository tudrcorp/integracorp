<?php

namespace App\Filament\Master\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Master\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCorporateQuoteRequest extends EditRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
