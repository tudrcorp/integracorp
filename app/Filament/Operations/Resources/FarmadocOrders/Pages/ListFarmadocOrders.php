<?php

namespace App\Filament\Operations\Resources\FarmadocOrders\Pages;

use App\Filament\Operations\Pages\FarmadocMedicationOrder;
use App\Filament\Operations\Resources\FarmadocOrders\Actions\FarmadocOrderActions;
use App\Filament\Operations\Resources\FarmadocOrders\FarmadocOrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFarmadocOrders extends ListRecords
{
    protected static string $resource = FarmadocOrderResource::class;

    protected static ?string $title = 'Pedidos a Farmadoc';

    protected function getHeaderActions(): array
    {
        return [
            FarmadocOrderActions::refreshOpenStatuses(),
            Action::make('newFarmadocOrder')
                ->label('Nuevo pedido')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->color('warning')
                ->visible(fn (): bool => FarmadocMedicationOrder::canAccess())
                ->url(fn (): string => FarmadocMedicationOrder::getUrl()),
        ];
    }
}
