<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\Supplier;
use App\Support\Filament\Operations\SupplierIntegracorpManagement;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SupplierIntegracorpPortalUsersPanel extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Supplier $supplier;

    public ?array $data = [];

    public function mount(Supplier $supplier): void
    {
        $this->supplier = $supplier;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model($this->supplier)
            ->components([
                SupplierIntegracorpManagement::portalUsersRepeater()
                    ->visible(true)
                    ->disabled(fn (): bool => ! SupplierIntegracorpManagement::userCanManage()),
            ]);
    }

    public function savePortalUsers(): void
    {
        if (! SupplierIntegracorpManagement::userCanManage()) {
            Notification::make()
                ->title('Acción no permitida')
                ->body('No tienes permiso para gestionar estos usuarios. Un SUPERADMIN puede asignártelo en Permisos → Operaciones.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->supplier->gestion_integracorp) {
            Notification::make()
                ->title('Gestión no habilitada')
                ->body('Active primero la gestión Integracorp para el proveedor.')
                ->warning()
                ->send();

            return;
        }

        $this->form->saveRelationships();

        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_PORTAL_USERS_SAVED', 'operations.suppliers.portal-users.save', [
            'supplier_id' => $this->supplier->id,
            'users_count' => $this->supplier->integracorpAnalysts()->count(),
        ]);

        Notification::make()
            ->title('Usuarios de acceso guardados')
            ->body('Se crearon o actualizaron las cuentas en la tabla de usuarios.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.operations.supplier-integracorp-portal-users-panel');
    }
}
