<?php

namespace App\Filament\Resources\CorporateQuoteRequests\Pages;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class EditCorporateQuoteRequest extends EditRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Detalle: Solicitud de cotización corporativa';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create-corporate-quote')
                ->label('Crear cotizacion')
                ->color('verde')
                ->icon('heroicon-o-plus')
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $this->redirect(route('filament.admin.resources.corporate-quotes.create', ['corporate_quote_request_id' => $this->record->id]));
                })
        ];
    }
}