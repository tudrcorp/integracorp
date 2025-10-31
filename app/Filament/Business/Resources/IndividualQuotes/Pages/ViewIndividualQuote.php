<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;

class ViewIndividualQuote extends ViewRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('back')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->url(IndividualQuoteResource::getUrl('index')),
        ];
    }
}