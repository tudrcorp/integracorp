<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Pages;

use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationOnCallUsers extends ListRecords
{
    protected static string $resource = OperationOnCallUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agregar Colaborador de Guardia')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}
