<?php

namespace App\Filament\General\Resources\IndividualQuotes\Pages;

use App\Models\IndividualQuote;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\General\Resources\IndividualQuotes\IndividualQuoteResource;

class ViewIndividualQuote extends ViewRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Información General';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-s-pencil')
                ->hidden(function (IndividualQuote $record) {
                    if ($record->status == 'APROBADA' || $record->status == 'EJECUTADA') {
                        return true;
                    }
                    return false;
                }),
            // ->url(IndividualQuoteResource::getUrl('edit', ['record' => $this->record->getKey()])),
        ];
    }
}