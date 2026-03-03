<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventory extends Model
{
    //
    protected $table = 'operation_inventories';

    protected $fillable = [
        'code',
        'name',
        'unit',
        'type',
        'existence',
        'cost',
        'created_by',
        'updated_by',
    ];

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
}
