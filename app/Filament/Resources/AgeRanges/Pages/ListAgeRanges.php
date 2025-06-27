<?php

namespace App\Filament\Resources\AgeRanges\Pages;

use App\Filament\Resources\AgeRanges\AgeRangeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgeRanges extends ListRecords
{
    protected static string $resource = AgeRangeResource::class;

    protected static ?string $title = 'RANGO DE EDADES';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-s-adjustments-vertical')
        ];
    }
}