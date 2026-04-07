<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationServiceOrder extends CreateRecord
{
    protected static string $resource = OperationServiceOrderResource::class;
}
