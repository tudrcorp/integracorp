<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\Pages;

use App\Filament\Agents\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIndividualQuote extends EditRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
