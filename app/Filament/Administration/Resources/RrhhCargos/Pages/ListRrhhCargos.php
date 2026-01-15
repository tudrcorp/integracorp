<?php

namespace App\Filament\Administration\Resources\RrhhCargos\Pages;

use App\Filament\Administration\Resources\RrhhCargos\RrhhCargoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhCargos extends ListRecords
{
    protected static string $resource = RrhhCargoResource::class;

    protected static ?string $title = "Gestion de Cargos";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label("Agregar Cargo")
                ->icon("heroicon-o-plus")
        ];
    }
}
