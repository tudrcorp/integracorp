<?php

namespace App\Filament\Administration\Resources\RrhhDeduccions\Pages;

use App\Filament\Administration\Resources\RrhhDeduccions\RrhhDeduccionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhDeduccions extends ListRecords
{
    protected static string $resource = RrhhDeduccionResource::class;

    protected static ?string $title = "Gestion de Deducciones";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->label("Agregar Deduccion")
            ->icon("heroicon-o-plus")
        ];
    }
}
