<?php

namespace App\Filament\Resources\CorporateQuoteRequests\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class ViewCorporateQuoteRequest extends ViewRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'INFORMACION GENERAL';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_quote')
                ->label('Crear cotización')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->action(function () {
                    // dd($this->record);
                    // session()->put('quote', $this->record);
                    // session()->put('quote_type', 'dress-taylor');

                    // $this->redirect(CorporateQuoteResource::getUrl('create'));

                    return redirect()->route('filament.admin.resources.corporate-quotes.create', ['record' => $this->record->id]);
                    
                }),
            
        ];
    }
}