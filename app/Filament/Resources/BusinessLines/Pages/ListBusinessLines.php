<?php

namespace App\Filament\Resources\BusinessLines\Pages;

use App\Filament\Resources\BusinessLines\BusinessLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBusinessLines extends ListRecords
{
    protected static string $resource = BusinessLineResource::class;

    protected static ?string $title = 'GESTIÓN DE LINEAS DE SERVICIO';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-s-user-plus')
        ];
    }
}