<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Pages;

use App\Filament\Administration\Resources\RrhhDepartamentos\RrhhDepartamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhDepartamentos extends ListRecords
{
    protected static string $resource = RrhhDepartamentoResource::class;

    protected static ?string $title = "Gestion de Departamentos";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label("Agregar Departamento")
                ->icon("heroicon-o-plus")
        ];
    }
}
