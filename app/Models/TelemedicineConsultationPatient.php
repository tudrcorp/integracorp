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
        'medicines',
        'indications',
        'labs',
        'studies',
        'created_by'
    ];

    protected $casts = [
        'labs'          => 'array',
        'studies'       => 'array',
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
}