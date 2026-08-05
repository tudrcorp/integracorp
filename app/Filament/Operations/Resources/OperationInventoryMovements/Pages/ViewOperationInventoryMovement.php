<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Pages;

use App\Filament\Operations\Resources\OperationInventoryMovements\OperationInventoryMovementResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOperationInventoryMovement extends ViewRecord
{
    protected static string $resource = OperationInventoryMovementResource::class;

    protected static ?string $title = 'Ver movimiento de inventario';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(OperationInventoryMovementResource::getUrl('index'))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }
}
