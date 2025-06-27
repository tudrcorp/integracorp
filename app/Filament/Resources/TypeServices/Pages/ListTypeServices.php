<?php

namespace App\Filament\Resources\TypeServices\Pages;

use App\Filament\Resources\TypeServices\TypeServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTypeServices extends ListRecords
{
    protected static string $resource = TypeServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
