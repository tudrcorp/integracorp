<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Pages;

use App\Filament\Business\Resources\DressTylorQuotes\DressTylorQuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDressTylorQuote extends EditRecord
{
    protected static string $resource = DressTylorQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
