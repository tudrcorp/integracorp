<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessUnit extends Model
{
    protected $table = 'business_units';

    protected $fillable = [
        'code',
        'definition',
        'status',
        'created_by',

    ];

    public function businessLine(): HasMany
    {
        return $this->hasMany(BusinessLine::class, 'business_unit_id', 'id');
    }

    // relacion 1 a 1 con OperationInventoryMovement
    public function operationInventoryMovements()
    {
        return $this->hasMany(OperationInventoryMovement::class);
    }
}
