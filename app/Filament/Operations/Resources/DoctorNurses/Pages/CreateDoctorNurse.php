<?php

namespace App\Filament\Operations\Resources\DoctorNurses\Pages;

use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctorNurse extends CreateRecord
{
    protected static string $resource = DoctorNurseResource::class;

    protected static ?string $title = 'Formulario de Creación del Proveedor Natural';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Proveedor natural creado')
            ->body('El proveedor natural ha sido creado exitosamente.');
    }
}
