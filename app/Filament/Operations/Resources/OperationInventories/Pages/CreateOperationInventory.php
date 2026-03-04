<?php

namespace App\Filament\Operations\Resources\OperationInventories\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Models\OperationInventoryEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateOperationInventory extends CreateRecord
{
    protected static string $resource = OperationInventoryResource::class;

    protected static ?string $title = 'Formulario de Registro de Producto/Medicamento';

    protected function afterCreate(): void
    {
        try {

            // 1.- Regitro la entrada de inventario
            $new_operation_inventory_entry = new OperationInventoryEntry;
            $new_operation_inventory_entry->operation_inventory_id = $this->getRecord()->id;
            $new_operation_inventory_entry->operation_inventory_type_id = $this->data['operation_inventory_type_id'];
            $new_operation_inventory_entry->quantity = $this->data['existence'];
            $new_operation_inventory_entry->type_entry = 'PRIMERA CARGA';
            $new_operation_inventory_entry->created_by = Auth::user()->name;
            $new_operation_inventory_entry->save();

        } catch (\Throwable $th) {
            
            Log::error('Error al registrar la entrada de inventario: '.$th->getMessage());
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }
}
