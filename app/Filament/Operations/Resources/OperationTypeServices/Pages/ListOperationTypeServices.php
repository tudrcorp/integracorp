<?php

namespace App\Filament\Operations\Resources\OperationTypeServices\Pages;

use App\Filament\Operations\Resources\OperationTypeServices\OperationTypeServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationTypeServices extends ListRecords
{
    protected static string $resource = OperationTypeServiceResource::class;

    protected static ?string $title = 'Tipos de Servicios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Tipo de Servicio')
                ->icon('heroicon-m-plus')
                ->color('primary'),
        ];
    }
}
