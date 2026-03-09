<?php

namespace App\Filament\Operations\Resources\OperationStatusServices\Pages;

use App\Filament\Operations\Resources\OperationStatusServices\OperationStatusServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationStatusService extends EditRecord
{
    protected static string $resource = OperationStatusServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
