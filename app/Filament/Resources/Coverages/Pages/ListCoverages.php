<?php

namespace App\Filament\Resources\Coverages\Pages;

use App\Filament\Resources\Coverages\CoverageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoverages extends ListRecords
{
    protected static string $resource = CoverageResource::class;

    protected static ?string $title = 'Gestión de Coberturas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-s-document-currency-dollar')
        ];
    }
}