<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventory extends Model
{
    //
    protected $table = 'operation_inventories';

    protected $fillable = [
        'name',
        'unit',
        'operation_inventory_type_id',
        'existence',
        'cost',
        'ubication',
        'created_by',
        'updated_by',
        'operation_inventory_principle_active_id',
        'laboratory',
        'min_stock',
        'location',
        'is_active',
        'operation_inventory_category_id',
        'barcode',
        'concentration',
        'image',
    ];

    public function operationInventoryCategory()
    {
        return $this->belongsTo(OperationInventoryCategory::class);
    }

    // Relacion 1 a N con OperationInventoryEntry
    // Entradas
    public function operationInventoryEntries()
    {
        return $this->hasMany(OperationInventoryEntry::class);
    }

    // Relacion 1 a N con OperationInventoryOutflow
    // Salidas
    public function operationInventoryOutflows()
    {
        return $this->hasMany(OperationInventoryOutflow::class);
    }

    // Relación 1 a 1 con OperationInventoryType (un inventario tiene un tipo)
    public function operationInventoryType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OperationInventoryType::class, 'operation_inventory_type_id', 'id');
    }

    // Relacion 1 a N con OperationInventoryPrincipleActive
    public function operationInventoryPrincipleActive()
    {
        return $this->belongsTo(OperationInventoryPrincipleActive::class);
    }
}
