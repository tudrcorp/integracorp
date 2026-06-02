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
        'equip_diag_vital_signs',
        'equip_desc_diag_vital_signs',
        'equip_diag_oximeter',
        'equip_desc_diag_oximeter',
        'equip_diag_thermometer',
        'equip_desc_diag_thermometer',
        'equip_diag_exam_kit',
        'equip_desc_diag_exam_kit',
        'equip_diag_glucometer',
        'equip_desc_diag_glucometer',
        'equip_diag_flashlight_hammer',
        'equip_desc_diag_flashlight_hammer',
        'equip_care_gloves',
        'equip_desc_care_gloves',
        'equip_care_antiseptics',
        'equip_desc_care_antiseptics',
        'equip_care_supplies',
        'equip_desc_care_supplies',
        'equip_care_sharps_container',
        'equip_desc_care_sharps_container',
        'equip_support_hygiene',
        'equip_desc_support_hygiene',
        'equip_support_scissors_forceps',
        'equip_desc_support_scissors_forceps',
        'equip_support_prescriptions_stamps',
        'equip_desc_support_prescriptions_stamps',
        'equip_adv_basic_medicines',
        'equip_desc_adv_basic_medicines',
        'equip_adv_catheters_aspiration',
        'equip_desc_adv_catheters_aspiration',
        'equip_adv_emergency_bag',
        'equip_desc_adv_emergency_bag',
    ];

    protected $casts = [
        'documents' => 'array',
        'equip_diag_vital_signs' => 'boolean',
        'equip_diag_oximeter' => 'boolean',
        'equip_diag_thermometer' => 'boolean',
        'equip_diag_exam_kit' => 'boolean',
        'equip_diag_glucometer' => 'boolean',
        'equip_diag_flashlight_hammer' => 'boolean',
        'equip_care_gloves' => 'boolean',
        'equip_care_antiseptics' => 'boolean',
        'equip_care_supplies' => 'boolean',
        'equip_care_sharps_container' => 'boolean',
        'equip_support_hygiene' => 'boolean',
        'equip_support_scissors_forceps' => 'boolean',
        'equip_support_prescriptions_stamps' => 'boolean',
        'equip_adv_basic_medicines' => 'boolean',
        'equip_adv_catheters_aspiration' => 'boolean',
        'equip_adv_emergency_bag' => 'boolean',
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
