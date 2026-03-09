<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Pages;

use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationOnCallUser extends EditRecord
{
    protected static string $resource = OperationOnCallUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
