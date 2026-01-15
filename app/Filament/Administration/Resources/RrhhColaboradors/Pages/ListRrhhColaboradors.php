<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Pages;

use App\Filament\Administration\Resources\RrhhColaboradors\RrhhColaboradorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhColaboradors extends ListRecords
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected static ?string $title = "Gestion de Colaboradores";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->label("Agregar Colaborador")
            ->icon("heroicon-o-plus")
        ];
    }
}
