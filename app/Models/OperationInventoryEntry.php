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
        'unit',
        'type',
        'created_by',
    ];

    // Relacion 1 a N
    public function operationInventory()
    {
        return $this->belongsTo(OperationInventory::class);
    }
}
