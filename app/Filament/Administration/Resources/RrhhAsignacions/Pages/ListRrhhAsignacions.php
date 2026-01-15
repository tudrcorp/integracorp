<?php

namespace App\Filament\Administration\Resources\RrhhAsignacions\Pages;

use App\Filament\Administration\Resources\RrhhAsignacions\RrhhAsignacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhAsignacions extends ListRecords
{
    protected static string $resource = RrhhAsignacionResource::class;

    protected static ?string $title = "Gestion de Asignaciones";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label("Agregar Asignacion")
                ->icon("heroicon-o-plus")
        ];
    }
}
