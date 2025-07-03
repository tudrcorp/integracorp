<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\Pages;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Agents\Resources\IndividualQuotes\IndividualQuoteResource;

class EditIndividualQuote extends EditRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Editar cotización individual';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->url(IndividualQuoteResource::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}