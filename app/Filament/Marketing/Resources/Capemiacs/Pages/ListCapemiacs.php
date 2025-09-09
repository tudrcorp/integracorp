<?php

namespace App\Filament\Marketing\Resources\Capemiacs\Pages;

use App\Filament\Marketing\Resources\Capemiacs\CapemiacResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCapemiacs extends ListRecords
{
    protected static string $resource = CapemiacResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
