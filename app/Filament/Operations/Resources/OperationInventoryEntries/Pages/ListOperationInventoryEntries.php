<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Pages;

use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventoryEntries extends ListRecords
{
    protected static string $resource = OperationInventoryEntryResource::class;

    protected static ?string $title = 'Entradas de Inventario';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
            Action::make('back')
                ->label('Volver')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url('/operations/operation-inventories'),
        ];
    }
}
