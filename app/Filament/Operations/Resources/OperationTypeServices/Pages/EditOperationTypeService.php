<?php

namespace App\Filament\Operations\Resources\OperationTypeServices\Pages;

use App\Filament\Operations\Resources\OperationTypeServices\OperationTypeServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationTypeService extends EditRecord
{
    protected static string $resource = OperationTypeServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
