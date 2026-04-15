<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorNurse extends Model
{
    // campos fillable state,city,coverage_zone,supplier_clasificacion_id,tipo_clinica,horario,status_convenio,status_sistena,name,rif,razon_social,personal_phone,local_phone,correo_principal,afiliacion_proveedor,ubicacion_principal,convenio_pago,tiempo_credito,created_by,updated_by,speciality
    protected $fillable = [
        'state',
        'city',
        'coverage_zone',
        'supplier_clasificacion_id',
        'tipo_clinica',
        'horario',
        'status_convenio',
        'status_sistema',
        'name',
        'rif',
        'razon_social',
        'personal_phone',
        'local_phone',
        'correo_principal',
        'afiliacion_proveedor',
        'ubicacion_principal',
        'convenio_pago',
        'tiempo_credito',
        'created_by',
        'updated_by',
        'speciality',
    ];

    public function supplierClasificacion()
    {
        return $this->belongsTo(SupplierClasificacion::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
