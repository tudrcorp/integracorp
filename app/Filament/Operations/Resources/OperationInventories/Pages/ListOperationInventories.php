<?php

namespace App\Filament\Operations\Resources\OperationInventories\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventories extends ListRecords
{
    protected static string $resource = OperationInventoryResource::class;

    protected static ?string $title = 'Inventario De Productos/Medicamentos';

    protected function getHeaderActions(): array
    {
        return [
            // Action::make('back')
            //     ->label('Volver')
            //     ->icon('heroicon-o-arrow-left')
            //     ->color('gray')
            //     ->url('/operations'),
            CreateAction::make()
                ->label('Nuevo Producto/Medicamento')
                ->icon('heroicon-o-plus')
                ->color('primary'),

        ];
    }
}
