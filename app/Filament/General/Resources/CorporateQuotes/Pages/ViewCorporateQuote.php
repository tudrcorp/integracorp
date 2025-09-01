<?php

namespace App\Filament\General\Resources\CorporateQuotes\Pages;

use App\Filament\General\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Models\CorporateQuote;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateQuote extends ViewRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Información General';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-s-pencil')
                ->hidden(function (CorporateQuote $record) {
                    if ($record->status == 'APROBADA' || $record->status == 'EJECUTADA') {
                        return true;
                    }
                    return false;
                }),
            // ->url(IndividualQuoteResource::getUrl('edit', ['record' => $this->record->getKey()])),
        ];
    }
}