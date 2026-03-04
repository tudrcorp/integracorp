<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Pages;

use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventoryOutflows extends ListRecords
{
    protected static string $resource = OperationInventoryOutflowResource::class;

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
