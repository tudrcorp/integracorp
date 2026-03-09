<?php

namespace App\Filament\Operations\Resources\OperationTypeNegotiations\Pages;

use App\Filament\Operations\Resources\OperationTypeNegotiations\OperationTypeNegotiationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationTypeNegotiation extends EditRecord
{
    protected static string $resource = OperationTypeNegotiationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
