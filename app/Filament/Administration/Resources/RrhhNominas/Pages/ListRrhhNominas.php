<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Pages;

use App\Filament\Administration\Resources\RrhhNominas\RrhhNominaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhNominas extends ListRecords
{
    protected static string $resource = RrhhNominaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
