<?php

namespace App\Filament\Business\Resources\AgeRanges\Pages;

use App\Filament\Business\Resources\AgeRanges\AgeRangeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgeRange extends ViewRecord
{
    protected static string $resource = AgeRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
