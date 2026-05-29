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
        'carta_acceptance',
        'documents',
        'local_beneficiary_name',
        'local_beneficiary_rif',
        'local_beneficiary_account_number',
        'local_beneficiary_account_bank',
        'local_beneficiary_account_type',
        'local_beneficiary_phone_pm',
        'local_beneficiary_account_number_mon_inter',
        'local_beneficiary_account_bank_mon_inter',
        'local_beneficiary_account_type_mon_inter',
        'extra_beneficiary_name',
        'extra_beneficiary_ci_rif',
        'extra_beneficiary_account_number',
        'extra_beneficiary_account_bank',
        'extra_beneficiary_account_type',
        'extra_beneficiary_route',
        'extra_beneficiary_swift',
        'extra_beneficiary_zelle',
        'extra_beneficiary_address',
    ];

    protected $casts = [
        'documents' => 'array',
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

    public function doctorNurseObservacions()
    {
        return $this->hasMany(DoctorNurseObservacion::class)->orderBy('created_at', 'desc');
    }
}
