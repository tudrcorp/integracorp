<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\OperationInventoryListHeaders;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListOperationInventoryOutflows extends ListRecords
{
    protected static string $resource = OperationInventoryOutflowResource::class;

    protected static ?string $title = 'Salidas de Inventario';

    public function getHeading(): string|Htmlable
    {
        return OperationInventoryListHeaders::outflows();
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
