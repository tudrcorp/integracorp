<?php

namespace App\Filament\Business\Resources\Cities\Pages;

use App\Filament\Business\Resources\Cities\CityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    protected static string $resource = CityResource::class;

    protected static ?string $title = 'Lista de Ciudades';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear ciudad')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}