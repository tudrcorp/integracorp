<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Pages;

use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOperationInventoryOutflow extends ViewRecord
{
    protected static string $resource = OperationInventoryOutflowResource::class;

    protected static ?string $title = 'Ver salida de inventario';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(OperationInventoryOutflowResource::getUrl('index'))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }
}
