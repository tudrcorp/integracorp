<?php

namespace App\Filament\Business\Resources\BusinessLines\Pages;

use App\Filament\Business\Resources\BusinessLines\BusinessLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBusinessLines extends ListRecords
{
    protected static string $resource = BusinessLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
