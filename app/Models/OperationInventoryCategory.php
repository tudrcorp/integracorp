<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventoryCategory extends Model
{
    //
    protected $table = 'operation_inventory_categories';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];
}
