<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Filament\Operations\Resources\OperationInventoryMovements\OperationInventoryMovementResource;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\OperationInventoryListHeaders;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListOperationInventoryMovements extends ListRecords
{
    protected static string $resource = OperationInventoryMovementResource::class;

    protected static ?string $title = 'Movimientos de Inventario';

    public function getHeading(): string|Htmlable
    {
        return OperationInventoryListHeaders::movements();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al inventario')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(OperationInventoryResource::getUrl('index'))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }
}
