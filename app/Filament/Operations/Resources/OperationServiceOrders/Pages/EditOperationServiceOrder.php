<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationServiceOrder extends EditRecord
{
    protected static string $resource = OperationServiceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
