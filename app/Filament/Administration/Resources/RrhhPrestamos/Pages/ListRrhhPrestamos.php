<?php

namespace App\Filament\Administration\Resources\RrhhPrestamos\Pages;

use App\Filament\Administration\Resources\RrhhPrestamos\RrhhPrestamoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhPrestamos extends ListRecords
{
    protected static string $resource = RrhhPrestamoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
