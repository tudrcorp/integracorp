<?php

namespace App\Filament\Operations\Resources\OperationTypeNegotiations\Pages;

use App\Filament\Operations\Resources\OperationTypeNegotiations\OperationTypeNegotiationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationTypeNegotiation extends ViewRecord
{
    protected static string $resource = OperationTypeNegotiationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
