<?php

namespace App\Filament\Operations\Resources\CorporateAllies\Pages;

use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCorporateAlly extends CreateRecord
{
    protected static string $resource = CorporateAllyResource::class;

    protected static ?string $title = 'Crear Aliado Corporativo';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Aliado corporativo creado')
            ->body('El aliado corporativo ha sido creado exitosamente.');
    }
}
