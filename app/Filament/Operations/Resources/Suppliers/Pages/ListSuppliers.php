<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Lista de Proveedores';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Proveedor')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}