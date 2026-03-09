<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Pages;

use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationOnCallUser extends ViewRecord
{
    protected static string $resource = OperationOnCallUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
