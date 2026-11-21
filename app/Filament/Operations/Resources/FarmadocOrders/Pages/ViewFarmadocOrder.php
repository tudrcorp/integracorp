<?php

namespace App\Filament\Operations\Resources\FarmadocOrders\Pages;

use App\Filament\Operations\Resources\FarmadocOrders\Actions\FarmadocOrderActions;
use App\Filament\Operations\Resources\FarmadocOrders\Actions\ReceiveFarmadocOrderAction;
use App\Filament\Operations\Resources\FarmadocOrders\FarmadocOrderResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewFarmadocOrder extends ViewRecord
{
    protected static string $resource = FarmadocOrderResource::class;

    protected static ?string $title = 'Pedido a Farmadoc';

    protected function getHeaderActions(): array
    {
        return [
            ReceiveFarmadocOrderAction::make()->after(fn () => $this->getRecord()->refresh()),
            FarmadocOrderActions::viewReceiptNote(),
            FarmadocOrderActions::viewDeliveryNote(),
            FarmadocOrderActions::downloadDeliveryNote(),
            FarmadocOrderActions::refreshStatus()->after(fn () => $this->getRecord()->refresh()),
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('gray')])
                ->url(FarmadocOrderResource::getUrl()),
        ];
    }
}
