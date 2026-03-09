<?php

namespace App\Filament\Operations\Resources\OperationStatusServices\Pages;

use App\Filament\Operations\Resources\OperationStatusServices\OperationStatusServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationStatusService extends CreateRecord
{
    protected static string $resource = OperationStatusServiceResource::class;
}
