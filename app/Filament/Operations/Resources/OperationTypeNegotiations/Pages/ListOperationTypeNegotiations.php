<?php

namespace App\Filament\Operations\Resources\OperationTypeNegotiations\Pages;

use App\Filament\Operations\Resources\OperationTypeNegotiations\OperationTypeNegotiationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationTypeNegotiations extends ListRecords
{
    protected static string $resource = OperationTypeNegotiationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
