<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineFollowUp extends Model
{
    protected $table = 'telemedicine_follow_ups';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'telemedicine_consultation_patient_id',
        'cuestion_1',
        'cuestion_2',
        'cuestion_3',
        'cuestion_4',
        'cuestion_5',
        'created_by',
        'next_follow_up',
        'hour',
        'code',
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

    
}