<?php

namespace App\Filament\Marketing\Resources\RrhhColaboradors\Pages;

use App\Filament\Marketing\Resources\RrhhColaboradors\RrhhColaboradorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhColaboradors extends ListRecords
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected static ?string $title = 'Lista de Colaboradores';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
