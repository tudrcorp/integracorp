<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicinePatientMedications extends Model
{
    protected $table = 'telemedicine_patient_medications';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'telemedicine_consultation_patient_id',
        'telemedicine_follow_up_id',
        'medicine',
        'indications',
        'telemedicine_priority_id',
        'duration',
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

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

    public function telemedicineFollowUp()
    {
        return $this->belongsTo(TelemedicineFollowUp::class);
    }

    public function telemedicinePriority()
    {
        return $this->belongsTo(TelemedicinePriority::class);
    }

    
}