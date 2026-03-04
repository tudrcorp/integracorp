<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventoryType extends Model
{
    //
    protected $table = 'operation_inventory_types';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    // Relación 1 a 1 con OperationInventory (un tipo tiene un inventario)
    public function operationInventory(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OperationInventory::class, 'operation_inventory_type_id', 'id');
    }
}
