<?php

namespace App\Filament\Resources\Logs\Pages;

use App\Filament\Resources\Logs\LogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLog extends ViewRecord
{
    protected static string $resource = LogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
