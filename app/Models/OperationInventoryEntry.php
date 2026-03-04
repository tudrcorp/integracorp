<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventoryEntry extends Model
{
    //
    protected $table = 'operation_inventory_entries';

    protected $fillable = [
        'operation_inventory_id',
        'quantity',
        'operation_inventory_type_id',
        'created_by',
        'type_entry',
    ];

    // Relación N a 1 con OperationInventory
    public function operationInventory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OperationInventory::class);
    }

    // Acceso al tipo a través del inventario (para usar en tablas/columnas)
    public function operationInventoryType(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            OperationInventoryType::class,
            OperationInventory::class,
            'operation_inventory_type_id',
            'id',
            'operation_inventory_id',
            'id'
        );
    }
}
