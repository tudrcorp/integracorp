<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationServiceOrders extends ListRecords
{
    protected static string $resource = OperationServiceOrderResource::class;

    protected static ?string $title = 'Gestión de Ordenes de Servicio';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
