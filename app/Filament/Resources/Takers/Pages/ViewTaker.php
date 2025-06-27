<?php

namespace App\Filament\Resources\Takers\Pages;

use App\Filament\Resources\Takers\TakerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTaker extends ViewRecord
{
    protected static string $resource = TakerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
