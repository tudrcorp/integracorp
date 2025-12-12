<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Editar Información del Proveedor';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;

        return $data;
    }

    protected function getSavedNotification(): ?Notification
{
    return Notification::make()
        ->icon('heroicon-s-check-circle')
        ->success()
        ->title('Proveedor Actualizado')
        ->body('El proveedor ha sido actualizado exitosamente.');
}

}