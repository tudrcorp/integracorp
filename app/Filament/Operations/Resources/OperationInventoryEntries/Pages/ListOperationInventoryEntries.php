<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\OperationInventoryListHeaders;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListOperationInventoryEntries extends ListRecords
{
    protected static string $resource = OperationInventoryEntryResource::class;

    protected static ?string $title = 'Entradas de Inventario';

    public function getHeading(): string|Htmlable
    {
        return OperationInventoryListHeaders::entries();
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
