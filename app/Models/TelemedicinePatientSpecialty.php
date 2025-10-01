<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicinePatientSpecialty extends Model
{
    protected $table = 'telemedicine_patient_specialties';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'telemedicine_consultation_patient_id',
        'type',
        'specialty',
        'assigned_by',
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(
            TelemedicinePatient::class,
            'telemedicine_patient_id',
            'id'
        );
    }

    public function telemedicineCase()
    {
        return $this->belongsTo(
            TelemedicineCase::class,
            'telemedicine_case_id',
            'id'
        );
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(
            TelemedicineDoctor::class,
            'telemedicine_doctor_id',
            'id'
        );
    }

    public function telemedicineConsultation()
    {
        return $this->belongsTo(
            TelemedicineConsultationPatient::class,
            'telemedicine_consultation_patient_id',
            'id'
        );
    }

    
}