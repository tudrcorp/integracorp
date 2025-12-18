<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Formulario de Creación del Proveedores';

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Proveedor creado')
            ->body('El proveedor ha sido creado exitosamente.');
    }
}