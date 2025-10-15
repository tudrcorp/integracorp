<?php

namespace App\Filament\Business\Resources\Coverages\Pages;

use App\Filament\Business\Resources\Coverages\CoverageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoverages extends ListRecords
{
    protected static string $resource = CoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cobertura')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}