<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListOperationInventoryOutflows extends ListRecords
{
    protected static string $resource = OperationInventoryOutflowResource::class;

    protected static ?string $title = 'Salidas de Inventario';

    public function getSubheading(): ?string
    {
        return 'Consulta salidas, ajustes y despachos de telemedicina por producto y almacén.';
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
