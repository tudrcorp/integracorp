<?php

namespace App\Filament\Business\Resources\BusinessLines\Pages;

use App\Filament\Business\Resources\BusinessLines\BusinessLineResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessLine extends ViewRecord
{
    protected static string $resource = BusinessLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
