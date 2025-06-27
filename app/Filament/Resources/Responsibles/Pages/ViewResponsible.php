<?php

namespace App\Filament\Resources\Responsibles\Pages;

use App\Filament\Resources\Responsibles\ResponsibleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewResponsible extends ViewRecord
{
    protected static string $resource = ResponsibleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
