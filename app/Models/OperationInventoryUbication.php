<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventoryUbication extends Model
{
    //
    protected $table = 'operation_inventory_ubications';

    protected $fillable = [
        'name',
        'address',
        'is_active',
    ];

    public function operationInventories()
    {
        return $this->hasMany(OperationInventory::class);
    }
}
