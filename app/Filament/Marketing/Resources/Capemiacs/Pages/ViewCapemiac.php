<?php

namespace App\Filament\Marketing\Resources\Capemiacs\Pages;

use App\Filament\Marketing\Resources\Capemiacs\CapemiacResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCapemiac extends ViewRecord
{
    protected static string $resource = CapemiacResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
