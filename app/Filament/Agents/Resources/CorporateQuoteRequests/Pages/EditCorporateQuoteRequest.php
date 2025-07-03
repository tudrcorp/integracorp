<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCorporateQuoteRequest extends EditRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Editar solicitud de cotización';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}