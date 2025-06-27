<?php

namespace App\Filament\Resources\Limits\Pages;

use App\Filament\Resources\Limits\LimitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLimit extends ViewRecord
{
    protected static string $resource = LimitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
