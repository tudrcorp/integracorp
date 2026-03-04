<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventoryOutflow extends Model
{
    //
    protected $table = 'operation_inventory_outflows';

    protected $fillable = [
        'operation_inventory_id',
        'quantity',
        'operation_inventory_type_id',
        'created_by',
        'type_outflow',
    ];

    // Relacion 1 a N con OperationInventory
    public function operationInventory()
    {
        return $this->belongsTo(OperationInventory::class);
    }

    public function operationInventoryType()
    {
        return $this->belongsTo(OperationInventoryType::class);
    }
}
