<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierEstatusSistema extends Model
{
    protected $table = 'supplier_estatus_sistemas';

    protected $fillable = [
        'supplier_id',
        'description',
        'created_by',
        'updated_by',
    ];

    public function supplier()
    {
        return $this->hasMany(Supplier::class);
    }
}