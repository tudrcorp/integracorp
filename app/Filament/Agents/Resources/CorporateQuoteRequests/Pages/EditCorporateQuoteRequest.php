<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Pages;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class EditCorporateQuoteRequest extends EditRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Editar solicitud de cotización';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->url(CorporateQuoteRequestResource::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}