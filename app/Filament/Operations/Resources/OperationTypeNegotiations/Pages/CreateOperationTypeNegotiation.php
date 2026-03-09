<?php

namespace App\Filament\Operations\Resources\OperationTypeNegotiations\Pages;

use App\Filament\Operations\Resources\OperationTypeNegotiations\OperationTypeNegotiationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationTypeNegotiation extends CreateRecord
{
    protected static string $resource = OperationTypeNegotiationResource::class;
}
