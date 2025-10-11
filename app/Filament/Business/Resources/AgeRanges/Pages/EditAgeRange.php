<?php

namespace App\Filament\Business\Resources\AgeRanges\Pages;

use App\Filament\Business\Resources\AgeRanges\AgeRangeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAgeRange extends EditRecord
{
    protected static string $resource = AgeRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
