<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineConsultationPatient extends Model
{
    protected $table = 'telemedicine_consultation_patients';

    protected $fillable = [
        'telemedicine_case_id',
        'telemedicine_case_code',
        'telemedicine_patient_id',
        'telemedicine_doctor_id',
        'code_reference',
        'full_name',
        'nro_identificacion',
        'type_service',
        'reason_consultation',
        'actual_phatology',
        'vs_pa',
        'vs_fc',
        'vs_fr',
        'vs_temp',
        'vs_sat',
        'vs_weight',
        'background',
        'diagnostic_impression',
        'labs',
        'studies',
        'other_labs',
        'other_studies',
        'consult_specialist',
        'other_specialist',
        'created_by',

        'hombre_izq',
        'hombro_der',
        'hombro_comp',
        
        'codo_izq',
        'codo_der',
        'codo_comp',
        
        'muneca_izq',
        'muneca_der',
        'muneca_comp',
        
        'mano_izq',
        'mano_der',
        'mano_comp',
        
        'humero_izq',
        'humero_der',
        'humero_comp',
        
        'ante_izq',
        'ante_der',
        'ante_comp',
        
        'cdl_ap',
        
        'pocep',
        
        'cc_ap',
        'cc_oblicuas',
        'cc_la_flexion',
        'cc_la_extension',
        
        'cls_ap',
        'cls_oblicuas',
        'cls_la_flexion',
        'cls_la_extension',
        
    ];

    protected $casts = [
        'labs'                  => 'array',
        'studies'               => 'array',
        'consult_specialist'    => 'array',
    ];

    
    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }

    public function telemedicineCase()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function telemedicinePatientMedications()
    {
        return $this->hasMany(TelemedicinePatientMedications::class);
    }

    public function telemedicineFollowUps()
    {
        return $this->hasMany(TelemedicineFollowUp::class);
    }

    public function telemedicineConsultationPatient()
    {
        return $this->hasMany(TelemedicineConsultationPatient::class, 'telemedicine_case_code', 'code');
    }
    
}