<?php

namespace App\Filament\Business\Resources\BusinessUnits\Pages;

use App\Filament\Business\Resources\BusinessUnits\BusinessUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBusinessUnits extends ListRecords
{
    protected static string $resource = BusinessUnitResource::class;

    protected static ?string $title = 'Formulario de creacion deUnidades de negocio';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear unidad de negocio')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}