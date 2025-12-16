<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierClasificacion extends Model
{
    protected $table = 'supplier_clasificacions';

    protected $fillable = [
        'description',
        'created_by',
        'updated_by',
    ];

    public function supplierTipoServicios()
    {
        return $this->hasMany(SupplierTipoServicio::class);
    }
}