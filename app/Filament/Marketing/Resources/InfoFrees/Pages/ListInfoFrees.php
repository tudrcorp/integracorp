<?php

namespace App\Filament\Marketing\Resources\InfoFrees\Pages;

use App\Filament\Marketing\Resources\InfoFrees\InfoFreeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInfoFrees extends ListRecords
{
    protected static string $resource = InfoFreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
