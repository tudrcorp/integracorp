<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierZonaCobertura extends Model
{
    protected $table = 'supplier_zona_coberturas';

    protected $fillable = [
        'supplier_id',
        'clasificacion_id',
        'type_service',
        'state_id',
        'city_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['type_service' => 'array'];

    public function supplierClasificacion()
    {
        return $this->belongsTo(SupplierClasificacion::class, 'clasificacion_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}