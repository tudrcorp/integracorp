<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineFollowUp extends Model
{
    protected $table = 'telemedicine_follow_ups';

    protected $fillable = [
        //Relaciones
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'telemedicine_consultation_patient_id',
        'telemedicine_service_list_id',

        //Informacion de paciente
        'full_name',
        'nro_identificacion',
        'reason_consultation',
        'actual_phatology',
        'background',
        'diagnostic_impression',
        
        //Cuentionarios
        'cuestion_1',
        'cuestion_2',
        'cuestion_3',
        'cuestion_4',
        'cuestion_5',
        'created_by',

        //Informacion de seguimiento
        'next_follow_up',
        'hour',
        'code',

        //Informacion de seguimiento
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
        'status',
    ];

    protected $casts = [
        'labs'                  => 'array',
        'studies'               => 'array',
        'consult_specialist'    => 'array',
    ];

    public function telemedicineCase()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }

    public function telemedicineConsultation()
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class);
    }

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineServiceList()
    {
        return $this->belongsTo(TelemedicineServiceList::class);
    }

    public function TelemedicinePatientMedications()
    {
        return $this->hasMany(TelemedicinePatientMedications::class);
    }

    
}