<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierTipoServicio extends Model
{
    protected $table = 'supplier_tipo_servicios';

    protected $fillable = [
        'supplier_clasificacion_id',
        'description',
        'created_by',
        'updated_by',
    ];

    public function supplierClasificacion()
    {
        return $this->belongsTo(SupplierClasificacion::class);
    }

}