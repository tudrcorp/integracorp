<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Support\Filament\Operations\SupplierIntegracorpManagementForm;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Formulario de Creación del Proveedores';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SupplierIntegracorpManagementForm::stripUnauthorizedFormData($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Proveedor creado')
            ->body('El proveedor ha sido creado exitosamente.');
    }

    protected function afterCreate(): void
    {
        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_CREATED', 'operations.suppliers.create', [
            'supplier_id' => $this->record->id,
            'supplier_name' => $this->record->name,
            'supplier_rif' => $this->record->rif,
            'supplier_status_convenio' => $this->record->status_convenio,
            'supplier_status_sistema' => $this->record->status_sistema,
            'created_by' => Auth::user()?->name,
        ]);
    }
}
