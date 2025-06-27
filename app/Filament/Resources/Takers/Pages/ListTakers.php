<?php

namespace App\Filament\Resources\Takers\Pages;

use App\Filament\Resources\Takers\TakerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTakers extends ListRecords
{
    protected static string $resource = TakerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
